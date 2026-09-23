<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Delineation;
use App\Models\MangroveData;
use App\Models\User;
use App\Models\UserActivity;
use App\Notifications\DelineationSubmittedForReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EndUserController extends Controller
{
    private const MAP_FEATURES_PER_DELINEATION = 20;

    public function dashboard()
    {
        return view('users.end-user', $this->buildDashboardData());
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

            $key = $type.':'.json_encode($coords);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'type' => $type,
                'coords' => $coords,
            ];

            if (count($out) >= self::MAP_FEATURES_PER_DELINEATION) {
                break;
            }
        }

        return $out;
    }

    private function delineationPayloadForMap(Delineation $delineation, array $extra = []): array
    {
        return array_merge($delineation->toArray(), $extra, [
            'features' => self::sanitizeFeaturesForMap($delineation->features),
        ]);
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

        if ($collection->contains(fn (array $row) => (int) ($row['id'] ?? 0) === (int) $payload['id'])) {
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

        $focus = Delineation::with('user:id,name,role')->find($focusId);
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

            $totalCoverage = MangroveData::sum('coverage_area_km2') ?? 0;
            $genusCount = MangroveData::distinct('genus_id')->count();
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
            $user->delineations(),
            Delineation::MAP_QUERY_LIMIT_OWN
        );
        $delineations = $delineationModels
            ->map(fn (Delineation $d) => $this->delineationPayloadForMap($d))
            ->values();

        $approvedForMapModels = Delineation::fetchForMap(
            Delineation::approved()->where('user_id', '!=', $user->id),
            Delineation::MAP_QUERY_LIMIT
        );

        $approvedDelineations = $approvedForMapModels;

        $approvedDelineationsForMap = $approvedForMapModels
            ->map(fn (Delineation $d) => $this->delineationPayloadForMap($d, [
                'is_system' => true,
                'approved' => true,
                'is_approved' => true,
            ]))
            ->values();

        $residentDelineationsForMap = collect();
        if ($user->isExpert()) {
            $residentDelineationsForMap = Delineation::fetchResidentDelineationsForExpertMap()
                ->map(fn (Delineation $d) => $this->delineationPayloadForMap($d))
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
            'approvedDelineations' => $approvedDelineations,
            'approvedDelineationsForMap' => $approvedDelineationsForMap,
            'residentDelineationsForMap' => $residentDelineationsForMap,
            'focusDelineationId' => $focusDelineationId,
            'focusDelineationRecord' => $focusDelineationRecord,
        ];
    }

    public function storeDelineation(Request $request)
    {
        $data = $request->validate([
            'features' => ['required', 'array', 'min:1', 'max:20'],
            'features.*.type' => ['required', 'string', 'in:point,line,area'],
            'features.*.coords' => ['required', 'array', 'min:1'],
            'name' => ['required', 'string', 'max:191'],
            'notes' => ['required', 'string'],
        ]);

        $features = collect($data['features'])->map(function ($feature) {
            return [
                'type' => $feature['type'],
                'coords' => $feature['coords'],
            ];
        })->values()->all();

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
