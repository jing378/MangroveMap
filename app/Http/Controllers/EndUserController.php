<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Delineation;
use App\Models\DelineationAnnualObservation;
use App\Models\MangroveData;
use App\Models\PlantingRecommendation;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\BoundaryResolver;
use App\Services\DelineationObservationRecorder;
use App\Notifications\DelineationSubmittedForReview;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EndUserController extends Controller
{
    private const MAP_FEATURES_PER_DELINEATION = 20;

    /** Expert Recommender-tab entries linked to a delineation (shown in its details panel). */
    private const PLANTING_RECOMMENDATIONS_RELATION = 'plantingRecommendations.user:id,name';

    /** Same cap as the expert Recommender tab. */
    private const PLANTING_RECOMMENDATIONS_MAP_LIMIT = 300;

    public const GENERA_REQUIRED_MESSAGE = 'Select at least one Mangrove Genus Present before saving.';

    /**
     * Every newly saved feature must record at least one non-blank genus.
     *
     * @param  array<int, array{genera?: mixed}>  $features
     */
    public static function assertFeaturesHaveGenera(array $features): void
    {
        foreach ($features as $feature) {
            $genera = array_filter(
                (array) ($feature['genera'] ?? []),
                fn($genus) => is_string($genus) && trim($genus) !== ''
            );

            if ($genera === []) {
                throw ValidationException::withMessages([
                    'genera' => self::GENERA_REQUIRED_MESSAGE,
                ]);
            }
        }
    }

    public function dashboard()
    {
        $data = $this->buildDashboardData();
        $data['page'] = 'dashboard';
        $data += $this->buildContributorData(Auth::user());
        return view('users.dashboard', $data);
    }

    /**
     * Community contributor stats for resident/expert dashboards (approved work only).
     */
    private function buildContributorData(User $user): array
    {
        $myApprovedCount = Delineation::approved()->where('user_id', $user->id)->count();

        $myContributorRank = null;
        if ($myApprovedCount > 0) {
            $myContributorRank = Delineation::approved()
                ->select('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > ?', [$myApprovedCount])
                ->get()
                ->count() + 1;
        }

        return [
            'communityContributors' => Delineation::approved()->distinct()->count('user_id'),
            'activeContributorsThisMonth' => Delineation::approved()
                ->where('approved_at', '>=', now()->subMonth())
                ->distinct()
                ->count('user_id'),
            'myContributorRank' => $myContributorRank,
            'topContributors' => Delineation::approved()
                ->selectRaw('user_id, COUNT(*) as approved_count')
                ->with('user:id,name,role')
                ->groupBy('user_id')
                ->orderByDesc('approved_count')
                ->take(5)
                ->get(),
        ];
    }

    public function map()
    {
        $data = $this->buildDashboardData();
        $data['page'] = 'map';
        $data['plantingRecommendationsForMap'] = PlantingRecommendation::query()
            ->visibleOnMapTo(Auth::user())
            ->with(['user:id,name', 'delineation:id,name'])
            ->latest()
            ->limit(self::PLANTING_RECOMMENDATIONS_MAP_LIMIT)
            ->get()
            // Site notes are the expert's working notes; the map popup does not show them.
            ->map(fn(PlantingRecommendation $recommendation) => Arr::except($recommendation->toMapPayload(), ['site_notes']))
            ->values();
        return view('users.map', $data);
    }

    public function delineate()
    {
        $data = $this->buildDashboardData();
        $data['page'] = 'delineate';
        return view('users.delineate', $data);
    }

    /**
     * Delineation saves accidentally duplicated the entire map into `features` (100k+ entries).
     * Keep map payloads small: valid geometries only, deduped, capped per record.
     */
    public static function sanitizeFeaturesForMap(mixed $features): array
    {
        if (! is_array($features)) {
            return [];
        }

        $out = [];
        $seen = [];

        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $type = $feature['type'] ?? null;
            if (! in_array($type, ['point', 'line', 'area'], true)) {
                continue;
            }

            $coords = $feature['coords'] ?? null;
            if (! is_array($coords) || $coords === []) {
                continue;
            }

            $key = $type . ':' . json_encode($coords);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'type' => $type,
                'coords' => $coords,
                'location' => $feature['location'] ?? null,
                'genera' => is_array($feature['genera'] ?? null) ? $feature['genera'] : [],
            ];

            if (count($out) >= self::MAP_FEATURES_PER_DELINEATION) {
                break;
            }
        }

        return $out;
    }

    private function delineationPayloadForMap(Delineation $delineation, array $extra = []): array
    {
        $latestRevision = $delineation->relationLoaded('latestRevision') ? $delineation->latestRevision : null;

        // Residents only see expert recommendations on approved delineations (same rule as the panel row).
        $viewer = request()->user();
        $plantingRecommendations = ($viewer?->isExpert() || $delineation->is_approved)
            ? $delineation->loadMissing(self::PLANTING_RECOMMENDATIONS_RELATION)->plantingRecommendations
                ->map(fn(PlantingRecommendation $recommendation) => $recommendation->toDelineationPanelPayload())
                ->values()
                ->all()
            : [];

        $payload = array_merge(Arr::except($delineation->toArray(), ['latest_revision']), $extra, [
            'planting_recommendations' => $plantingRecommendations,
            'features' => self::sanitizeFeaturesForMap($delineation->features),
            'user_name' => $delineation->user?->name ?? null,
            'created_by' => $delineation->user?->name ?? null,
            'approved_by_name' => $delineation->approvedBy?->name ?? $delineation->approved_by_name ?? null,
            'last_revised_by_name' => $latestRevision?->reviser?->name,
            'last_revised_at' => $latestRevision?->created_at?->toIso8601String(),
        ]);

        if ($delineation->relationLoaded('user') || $delineation->user !== null) {
            $payload['user'] = $delineation->user?->only(['id', 'name', 'role']);
        }

        return $payload;
    }

    private function userCanViewDelineationOnMap(User $user, Delineation $delineation): bool
    {
        if ($user->isExpert()) {
            return $delineation->user_id === $user->id
                || $delineation->user?->role === 'end_user';
        }

        return $delineation->user_id === $user->id
            || ($delineation->is_approved && ! $delineation->is_rejected);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $records
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function prependUniqueDelineationPayload($records, array $payload): \Illuminate\Support\Collection
    {
        $collection = collect($records);

        if ($collection->contains(fn(array $row) => (int) ($row['id'] ?? 0) === (int) $payload['id'])) {
            return $collection->values();
        }

        return $collection->prepend($payload)->values();
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection|\Illuminate\Support\LazyCollection, 3: ?array, 4: ?int}
     */
    private function applyFocusDelineationForMap(
        User $user,
        $delineations,
        $approvedDelineationsForMap,
        $residentDelineationsForMap,
    ): array {
        $focusId = request()->integer('delineation');
        if (! $focusId) {
            return [$delineations, $approvedDelineationsForMap, $residentDelineationsForMap, null, null];
        }

        $focus = Delineation::with(['user:id,name,role', 'approvedBy:id,name', 'latestRevision.reviser:id,name'])->find($focusId);
        if (! $focus || ! $this->userCanViewDelineationOnMap($user, $focus)) {
            return [$delineations, $approvedDelineationsForMap, $residentDelineationsForMap, null, $focusId];
        }

        $payload = $this->delineationPayloadForMap($focus);

        if ($user->isExpert()) {
            if ($focus->user_id === $user->id) {
                $delineations = $this->prependUniqueDelineationPayload($delineations, $payload);
            } else {
                $residentDelineationsForMap = $this->prependUniqueDelineationPayload($residentDelineationsForMap, $payload);
            }
        } elseif ($focus->user_id === $user->id) {
            $delineations = $this->prependUniqueDelineationPayload($delineations, $payload);
        } elseif ($focus->is_approved) {
            $approvedDelineationsForMap = $this->prependUniqueDelineationPayload($approvedDelineationsForMap, array_merge($payload, [
                'is_system' => true,
                'approved' => true,
                'is_approved' => true,
            ]));
        }

        return [$delineations, $approvedDelineationsForMap, $residentDelineationsForMap, $payload, $focusId];
    }

    public function buildDashboardData(): array
    {
        $user = Auth::user();

        $userAnalyses = Analysis::where('user_id', $user->id)
            ->latest()
            ->get();

        $totalAnalyses = $userAnalyses->count();
        $completedAnalyses = $userAnalyses->where('status', 'completed')->count();
        $pendingAnalyses = $userAnalyses->where('status', 'pending')->count();
        $failedAnalyses = $userAnalyses->where('status', 'failed')->count();
        $recentAnalyses = $userAnalyses->take(5);

        $totalCoverage = (MangroveData::sum('coverage_area_km2') ?? 0) * 100;
        $genusCount = MangroveData::whereNotNull('genus_id')->distinct()->count('genus_id');
        $degradedArea = MangroveData::where('health_status', 'degraded')->sum('coverage_area_km2') ?? 0;

        $genusDistribution = MangroveData::select('genus_id', DB::raw('COUNT(*) as count'))
            ->whereNotNull('genus_id')
            ->groupBy('genus_id')
            ->with('genus')
            ->limit(10)
            ->get();

        $genusLabels = [];
        $genusSeries = [];
        foreach ($genusDistribution as $item) {
            if ($item->genus) {
                $genusLabels[] = $item->genus->common_name;
                $genusSeries[] = $item->count;
            }
        }

        $coverageTrends = MangroveData::select(
            DB::raw('YEAR(observation_date) as year'),
            DB::raw('SUM(coverage_area_km2) as total')
        )
            ->groupBy(DB::raw('YEAR(observation_date)'))
            ->orderBy('year')
            ->get();

        $trendYears = $coverageTrends->pluck('year')->toArray();
        $trendValues = $coverageTrends->pluck('total')->toArray();

        $delineationModels = Delineation::fetchForMap(
            $user->delineations()->with(['user:id,name,role', 'approvedBy:id,name']),
            Delineation::MAP_QUERY_LIMIT_OWN,
            ['latestRevision.reviser:id,name', self::PLANTING_RECOMMENDATIONS_RELATION]
        );
        $delineations = $delineationModels
            ->map(fn(Delineation $d) => $this->delineationPayloadForMap($d))
            ->values();

        $approvedForMapModels = Delineation::fetchForMap(
            Delineation::approved()->where('user_id', '!=', $user->id)->with(['user:id,name,role', 'approvedBy:id,name']),
            Delineation::MAP_QUERY_LIMIT,
            [self::PLANTING_RECOMMENDATIONS_RELATION]
        );

        $approvedDelineations = $approvedForMapModels;

        $approvedDelineationsForMap = $approvedForMapModels
            ->map(fn(Delineation $d) => $this->delineationPayloadForMap($d, [
                'is_system' => true,
                'approved' => true,
                'is_approved' => true,
            ]))
            ->values();

        $residentDelineationsForMap = collect();
        if ($user->isExpert()) {
            $residentDelineationsForMap = Delineation::fetchResidentDelineationsForExpertMap()
                ->load(['user:id,name,role', 'approvedBy:id,name', 'latestRevision.reviser:id,name', self::PLANTING_RECOMMENDATIONS_RELATION])
                ->map(fn(Delineation $d) => $this->delineationPayloadForMap($d))
                ->values();
        }

        [
            $delineations,
            $approvedDelineationsForMap,
            $residentDelineationsForMap,
            $focusDelineationRecord,
            $focusDelineationId,
        ] = $this->applyFocusDelineationForMap(
            $user,
            $delineations,
            $approvedDelineationsForMap,
            $residentDelineationsForMap,
        );

        return [
            'user' => $user,
            'totalAnalyses' => $totalAnalyses,
            'completedAnalyses' => $completedAnalyses,
            'pendingAnalyses' => $pendingAnalyses,
            'failedAnalyses' => $failedAnalyses,
            'recentAnalyses' => $recentAnalyses,
            'totalCoverage' => $totalCoverage,
            'genusCount' => $genusCount,
            'degradedArea' => $degradedArea,
            'genusLabels' => $genusLabels,
            'genusSeries' => $genusSeries,
            'trendYears' => $trendYears,
            'trendValues' => $trendValues,
            'delineations' => $delineations,
            'dashboardDelineations' => $user->isExpert() ? $residentDelineationsForMap : $delineations,
            'approvedDelineations' => $approvedDelineations,
            'approvedDelineationsForMap' => $approvedDelineationsForMap,
            'residentDelineationsForMap' => $residentDelineationsForMap,
            'focusDelineationId' => $focusDelineationId,
            'focusDelineationRecord' => $focusDelineationRecord,
        ];
    }

    public function storeDelineation(Request $request, BoundaryResolver $boundaryResolver)
    {
        $data = $request->validate([
            'features' => ['required', 'array', 'min:1', 'max:20'],
            'features.*.type' => ['required', 'string', 'in:point,line,area'],
            'features.*.coords' => ['required', 'array', 'min:1'],
            'features.*.location' => ['nullable', 'string', 'max:255'],
            'features.*.genera' => ['nullable', 'array'],
            'features.*.genera.*' => ['string', 'max:100'],
            'name' => ['required', 'string', 'max:191'],
            'notes' => ['required', 'string'],
            'genera' => ['nullable', 'array'],
            'genera.*' => ['string', 'max:100'],
        ]);

        $features = collect($data['features'])->map(function ($feature) use ($data) {
            return [
                'type' => $feature['type'],
                'coords' => $feature['coords'],
                'genera' => $feature['genera'] ?? $data['genera'] ?? [],
            ];
        })->values()->all();
        self::assertFeaturesHaveGenera($features);
        $features = $boundaryResolver->resolveFeatures($features);

        $delineation = Delineation::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'] ?? 'Saved delineation',
            'notes' => $data['notes'] ?? null,
            'features' => $features,
        ]);

        UserActivity::recordDelineationSubmitted($delineation);

        User::where('role', 'expert')
            ->get()
            ->each(fn($expert) => $expert->notify(new DelineationSubmittedForReview($delineation)));

        return response()->json([
            'message' => 'Delineation saved and submitted for expert review.',
            'delineation' => $delineation,
        ]);
    }

    public function reviseRejectedDelineation(
        Request $request,
        Delineation $delineation,
        BoundaryResolver $boundaryResolver,
    ) {
        abort_unless((int) $delineation->user_id === (int) $request->user()->id, 403);
        abort_unless($delineation->is_rejected && ! $delineation->is_approved, 409, 'Only rejected delineations can be revised.');

        $data = $request->validate([
            'features' => ['required', 'array', 'min:1', 'max:20'],
            'features.*.type' => ['required', 'string', 'in:point,line,area'],
            'features.*.coords' => ['required', 'array', 'min:1', 'max:10000'],
            'features.*.genera' => ['nullable', 'array'],
            'features.*.genera.*' => ['string', 'max:100'],
            'name' => ['required', 'string', 'max:191'],
            'notes' => ['required', 'string'],
        ]);

        $features = collect($data['features'])->map(fn(array $feature) => [
            'type' => $feature['type'],
            'coords' => $feature['coords'],
            'genera' => $feature['genera'] ?? [],
        ])->values()->all();
        $features = $boundaryResolver->resolveFeatures($features);
        $delineationId = $delineation->id;

        DB::transaction(function () use ($request, $delineationId, $data, $features, &$delineation): void {
            $delineation = Delineation::query()->lockForUpdate()->findOrFail($delineationId);
            abort_unless((int) $delineation->user_id === (int) $request->user()->id, 403);
            abort_unless($delineation->is_rejected && ! $delineation->is_approved, 409, 'Only rejected delineations can be revised.');

            $delineation->revisions()->create([
                'revised_by' => $request->user()->id,
                'version' => ((int) $delineation->revisions()->max('version')) + 1,
                'snapshot' => [
                    'name' => $delineation->name,
                    'notes' => $delineation->notes,
                    'features' => $delineation->features,
                    'is_approved' => $delineation->is_approved,
                    'approved_at' => $delineation->approved_at,
                    'approved_by' => $delineation->approved_by,
                    'is_rejected' => $delineation->is_rejected,
                    'rejected_at' => $delineation->rejected_at,
                    'rejected_by' => $delineation->rejected_by,
                    'rejection_notes' => $delineation->rejection_notes,
                    'planting_recommendation' => $delineation->planting_recommendation,
                ],
            ]);

            $delineation->update([
                'name' => $data['name'],
                'notes' => $data['notes'],
                'features' => $features,
                'is_approved' => false,
                'approved_at' => null,
                'approved_by' => null,
                'is_rejected' => false,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_notes' => null,
                'planting_recommendation' => null,
            ]);
        });

        $delineation->load('user');
        UserActivity::recordDelineationResubmitted($delineation);

        User::where('role', 'expert')
            ->get()
            ->each(fn($expert) => $expert->notify(new DelineationSubmittedForReview($delineation, true)));

        return response()->json([
            'message' => 'Revised delineation submitted for expert review.',
            'delineation' => $delineation,
        ]);
    }

    /**
     * Who may adjust the boundary of an existing saved delineation (in place, no new record).
     * Experts: their own records and any resident (end_user) record, regardless of review status.
     * Residents: their own records that are still pending review (rejected ones use the revision flow).
     */
    private function userCanEditDelineationBoundary(User $user, Delineation $delineation): bool
    {
        if ($user->isExpert()) {
            return (int) $delineation->user_id === (int) $user->id
                || $delineation->user?->role === 'end_user';
        }

        return (int) $delineation->user_id === (int) $user->id
            && ! $delineation->is_approved
            && ! $delineation->is_rejected;
    }

    private static function isLatLngPair(mixed $pair): bool
    {
        return is_array($pair)
            && count($pair) >= 2
            && is_numeric($pair[0] ?? null)
            && is_numeric($pair[1] ?? null)
            && abs((float) $pair[0]) <= 90
            && abs((float) $pair[1]) <= 180;
    }

    /**
     * Update the geometry of an existing delineation in place.
     * Keeps the original delineator (user_id), name, notes and review status; the previous state is
     * stored in delineation_revisions with `revised_by` = the editor.
     */
    public function updateDelineationBoundary(
        Request $request,
        Delineation $delineation,
        BoundaryResolver $boundaryResolver,
        DelineationObservationRecorder $observationRecorder,
    ) {
        $user = $request->user();
        $delineation->loadMissing('user:id,name,role');
        abort_unless($this->userCanEditDelineationBoundary($user, $delineation), 403, 'You are not allowed to edit this delineation.');

        $data = $request->validate([
            'features' => ['required', 'array', 'min:1', 'max:20'],
            'features.*.type' => ['required', 'string', 'in:point,line,area'],
            'features.*.coords' => ['required', 'array', 'min:1', 'max:10000'],
            'features.*.genera' => ['nullable', 'array'],
            'features.*.genera.*' => ['string', 'max:100'],
            // Experts say whether the edit fixes the drawing or records a real change in the mangroves.
            'record_as' => ['nullable', 'string', 'in:correction,observation'],
            // The app clock is UTC; "today" is judged in local (Philippine) time like the date picker.
            'observation_date' => ['exclude_unless:record_as,observation', 'required', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:' . now(DelineationAnnualObservation::LOCAL_TIMEZONE)->toDateString()],
            'observation_source' => ['exclude_unless:record_as,observation', 'required', 'string', 'in:' . implode(',', array_keys(DelineationAnnualObservation::SOURCES))],
        ], [
            'observation_date.required' => 'Enter the date the change was observed.',
            'observation_date.before_or_equal' => 'The observation date cannot be in the future.',
            'observation_date.after_or_equal' => 'The observation date must be in 2000 or later.',
            'observation_source.required' => 'Choose where the change was observed.',
            'observation_source.in' => 'Choose where the change was observed.',
        ]);

        $recordsObservation = ($data['record_as'] ?? 'correction') === 'observation';
        if ($recordsObservation) {
            abort_unless($user->isExpert(), 403, 'Only experts can record observed changes.');
            if ($delineation->is_rejected) {
                throw ValidationException::withMessages([
                    'record_as' => 'Rejected delineations cannot have observed changes. Save it as a correction instead.',
                ]);
            }
        }

        $features = [];
        foreach ($data['features'] as $index => $feature) {
            $coords = $feature['coords'];
            if ($feature['type'] === 'point') {
                $valid = self::isLatLngPair($coords);
                $coords = $valid ? [(float) $coords[0], (float) $coords[1]] : $coords;
            } else {
                $valid = collect($coords)->every(fn($pair) => self::isLatLngPair($pair));
                if ($valid) {
                    $coords = collect($coords)->map(fn($pair) => [(float) $pair[0], (float) $pair[1]])->values()->all();
                    $distinct = collect($coords)->map(fn($pair) => $pair[0] . ',' . $pair[1])->unique()->count();
                    $valid = $distinct >= ($feature['type'] === 'area' ? 3 : 2);
                }
            }

            if (! $valid) {
                return response()->json([
                    'message' => $feature['type'] === 'area'
                        ? 'A polygon boundary needs at least 3 distinct points.'
                        : 'The edited boundary contains invalid coordinates.',
                    'errors' => ["features.$index.coords" => ['Invalid coordinates.']],
                ], 422);
            }

            $features[] = [
                'type' => $feature['type'],
                'coords' => $coords,
                'genera' => $feature['genera'] ?? [],
            ];
        }

        $features = $boundaryResolver->resolveFeatures($features);
        $delineationId = $delineation->id;

        if ($recordsObservation && DelineationObservationRecorder::areaHectares($features) <= 0) {
            throw ValidationException::withMessages([
                'record_as' => 'An observed change needs at least one area shape so its hectares can be measured.',
            ]);
        }

        $observation = null;
        DB::transaction(function () use ($user, $delineationId, $features, $recordsObservation, $data, $observationRecorder, &$delineation, &$observation): void {
            $delineation = Delineation::query()->lockForUpdate()->findOrFail($delineationId);
            $delineation->loadMissing('user:id,name,role');
            abort_unless($this->userCanEditDelineationBoundary($user, $delineation), 403, 'You are not allowed to edit this delineation.');

            $delineation->revisions()->create([
                'revised_by' => $user->id,
                'version' => ((int) $delineation->revisions()->max('version')) + 1,
                'snapshot' => [
                    'type' => 'boundary_edit',
                    'name' => $delineation->name,
                    'notes' => $delineation->notes,
                    'features' => $delineation->features,
                    'is_approved' => $delineation->is_approved,
                    'is_rejected' => $delineation->is_rejected,
                ],
            ]);

            $previousFeatures = $delineation->features ?? [];

            // Only the geometry changes: owner, name, notes and review status are preserved.
            $delineation->update(['features' => $features]);

            if ($recordsObservation) {
                $observation = $observationRecorder->record(
                    $delineation,
                    $previousFeatures,
                    $features,
                    Carbon::parse($data['observation_date']),
                    DelineationAnnualObservation::SOURCES[$data['observation_source']],
                    $user,
                );
            }
        });

        $delineation->load(['user:id,name,role', 'approvedBy:id,name', 'latestRevision.reviser:id,name']);

        return response()->json([
            'message' => $observation
                ? "Boundary updated and recorded as the {$observation->year} observation ("
                    . number_format($observation->coverage_area_ha, 2) . ' ha).'
                : 'Delineation boundary updated.',
            'delineation' => $this->delineationPayloadForMap($delineation),
            'observation_year' => $observation?->year,
        ]);
    }

    public function previewDelineationLocation(Request $request, BoundaryResolver $boundaryResolver)
    {
        $data = $request->validate([
            'feature.type' => ['required', 'string', 'in:point,line,area'],
            'feature.coords' => ['required', 'array', 'min:1', 'max:10000'],
        ]);

        $feature = $boundaryResolver->resolveFeatures([[
            'type' => $data['feature']['type'],
            'coords' => $data['feature']['coords'],
        ]])[0];

        return response()->json(['feature' => $feature]);
    }

    public function destroyDelineation(Request $request, Delineation $delineation)
    {
        if ($delineation->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($delineation->is_approved) {
            return response()->json([
                'message' => 'Approved delineations cannot be deleted.',
            ], 422);
        }

        $delineation->delete();

        return response()->json([
            'message' => 'Delineation deleted.',
        ]);
    }
}
