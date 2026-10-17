<?php

namespace App\Http\Controllers;

use App\Models\Delineation;
use App\Models\DelineationAnnualObservation;
use App\Models\Genus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DelineationAnnualObservationController extends Controller
{
    public function index(Request $request, Delineation $delineation): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (
            $user->isExpert()
            || $delineation->user_id === $user->id
            || ($delineation->is_approved && ! $delineation->is_rejected)
        ), 404);

        $observations = $delineation->annualObservations()
            ->with('boundaryRevisions')
            ->orderBy('year')
            ->get();
        $features = $delineation->features ?? [];
        $coverage = $observations->map(function (DelineationAnnualObservation $observation) use (&$features) {
            $features = $observation->boundaryRevisions->last()?->features ?? $features;

            return [
                'year' => $observation->year,
                'coverageAreaHa' => $observation->coverage_area_ha,
                'source' => $observation->source,
                'features' => $features,
            ];
        })->values();
        $latest = $observations->last();
        $genusIds = collect($latest?->genus_distribution ?? [])->keys();
        $genera = Genus::whereIn('id', $genusIds)->pluck('common_name', 'id');

        $genusDistribution = collect($latest?->genus_distribution ?? [])
            ->map(function ($share, $genusId) use ($genera) {
                return [
                    'label' => $genera[$genusId] ?? null,
                    'share' => (float) $share,
                ];
            })
            ->filter(fn($item) => $item['label'] !== null && $item['share'] > 0)
            ->values();

        return response()->json([
            'coverage' => $coverage,
            'genusYear' => $latest?->year,
            'genusDistribution' => $genusDistribution,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->isExpert(), 404);

        $delineations = Delineation::with('user:id,name')
            ->where('is_rejected', false)
            ->orderByDesc('created_at')
            ->get();
        $genera = Genus::orderBy('common_name')->get(['id', 'common_name']);
        $observations = DelineationAnnualObservation::with('delineation', 'creator')
            ->orderByDesc('year')
            ->orderByDesc('observation_date')
            ->paginate(20);
        $editingObservation = $request->filled('observation')
            ? DelineationAnnualObservation::with(['delineation', 'boundaryRevisions.creator'])
            ->findOrFail($request->integer('observation'))
            : null;
        $boundaryHistory = $editingObservation?->boundaryRevisions ?? collect();
        $baselineFeatures = $editingObservation?->delineation?->features ?? [];
        $editingBoundaryFeatures = $boundaryHistory->last()?->features ?? $baselineFeatures;

        return view('users.annual-observations', compact(
            'delineations',
            'genera',
            'observations',
            'editingObservation',
            'boundaryHistory',
            'baselineFeatures',
            'editingBoundaryFeatures'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isExpert(), 404);

        $validated = $request->validate([
            'observation_id' => ['nullable', 'integer', 'exists:delineation_annual_observations,id'],
            'delineation_id' => ['required', 'integer', 'exists:delineations,id'],
            'year' => ['required', 'integer', 'between:2000,' . now()->year],
            'observation_date' => ['required', 'date'],
            'coverage_area_ha' => ['required', 'numeric', 'min:0'],
            'source' => ['required', 'string', 'max:255'],
            'genus_distribution' => ['nullable', 'array'],
            'genus_distribution.*' => ['nullable', 'numeric', 'between:0,100'],
            'features' => ['required', 'array', 'min:1', 'max:20'],
            'features.*' => ['required', 'array'],
            'features.*.type' => ['required', 'in:point,line,area'],
            'features.*.coords' => ['required', 'array'],
        ]);

        $features = $this->validatedFeatures($validated['features']);

        if (Carbon::parse($validated['observation_date'])->year !== (int) $validated['year']) {
            return back()->withErrors([
                'observation_date' => 'The observation date must fall within the selected year.',
            ])->withInput();
        }

        $distribution = collect($validated['genus_distribution'] ?? [])
            ->filter(fn($share) => $share !== null && (float) $share > 0)
            ->map(fn($share) => (float) $share)
            ->all();

        if ($distribution !== []) {
            $validGenusCount = Genus::whereIn('id', array_keys($distribution))->count();
            if ($validGenusCount !== count($distribution)) {
                return back()->withErrors([
                    'genus_distribution' => 'One or more selected genera are invalid.',
                ])->withInput();
            }

            if (abs(array_sum($distribution) - 100) > 0.1) {
                return back()->withErrors([
                    'genus_distribution' => 'Genus shares must total 100% when provided.',
                ])->withInput();
            }
        }

        $savedBoundaryVersion = DB::transaction(function () use ($request, $validated, $distribution, $features) {
            $observation = isset($validated['observation_id'])
                ? DelineationAnnualObservation::query()->lockForUpdate()->findOrFail($validated['observation_id'])
                : new DelineationAnnualObservation();

            if ($observation->exists && (int) $observation->delineation_id !== (int) $validated['delineation_id']) {
                throw ValidationException::withMessages([
                    'delineation_id' => 'An annual observation cannot be moved to a different delineation.',
                ]);
            }

            $duplicateYear = DelineationAnnualObservation::query()
                ->where('delineation_id', $validated['delineation_id'])
                ->where('year', $validated['year'])
                ->when($observation->exists, fn($query) => $query->where('id', '!=', $observation->id))
                ->exists();

            if ($duplicateYear) {
                throw ValidationException::withMessages([
                    'year' => 'This delineation already has an observation for that year. Edit the existing record instead.',
                ]);
            }

            $delineation = $observation->exists
                ? $observation->delineation
                : Delineation::findOrFail($validated['delineation_id']);
            $latestRevision = $observation->exists
                ? $observation->boundaryRevisions()->orderByDesc('version')->first()
                : null;
            $previousFeatures = $latestRevision?->features ?? $delineation->features ?? [];

            $observation->fill([
                'delineation_id' => $validated['delineation_id'],
                'year' => $validated['year'],
                'observation_date' => $validated['observation_date'],
                'coverage_area_ha' => $validated['coverage_area_ha'],
                'genus_distribution' => $distribution ?: null,
                'source' => $validated['source'],
            ]);

            if (! $observation->exists) {
                $observation->created_by = $request->user()->id;
            }

            $observation->save();

            if ($this->normalizeFeatures($features) === $this->normalizeFeatures($previousFeatures)) {
                return null;
            }

            $version = ($latestRevision?->version ?? 0) + 1;
            $observation->boundaryRevisions()->create([
                'version' => $version,
                'features' => $features,
                'created_by' => $request->user()->id,
            ]);

            return $version;
        });

        return redirect()->route('expert.annual-observations.create')
            ->with('success', $savedBoundaryVersion === null
                ? 'Annual observation saved. The boundary was unchanged.'
                : "Annual observation saved with boundary revision {$savedBoundaryVersion}.");
    }

    /**
     * @param  array<int, array{type: string, coords: array}>  $features
     * @return array<int, array{type: string, coords: array}>
     */
    private function validatedFeatures(array $features): array
    {
        foreach ($features as $index => $feature) {
            $type = $feature['type'] ?? null;
            $coords = $feature['coords'] ?? null;

            if ($type === 'point') {
                $positions = [$coords];
                $minimumPositions = 1;
            } elseif (in_array($type, ['line', 'area'], true)) {
                $positions = $coords;
                $minimumPositions = $type === 'line' ? 2 : 3;
            } else {
                throw ValidationException::withMessages([
                    "features.{$index}.type" => 'Each boundary feature must be a point, line, or area.',
                ]);
            }

            if (! is_array($positions) || count($positions) < $minimumPositions) {
                throw ValidationException::withMessages([
                    "features.{$index}.coords" => 'The boundary geometry has too few coordinates.',
                ]);
            }

            foreach ($positions as $position) {
                if (
                    ! is_array($position) || ! array_is_list($position) || count($position) !== 2
                    || ! is_numeric($position[0]) || ! is_numeric($position[1])
                    || (float) $position[0] < -90 || (float) $position[0] > 90
                    || (float) $position[1] < -180 || (float) $position[1] > 180
                ) {
                    throw ValidationException::withMessages([
                        "features.{$index}.coords" => 'Boundary coordinates must be valid latitude and longitude pairs.',
                    ]);
                }
            }

            $features[$index] = [
                'type' => $type,
                'coords' => $type === 'point'
                    ? [(float) $coords[0], (float) $coords[1]]
                    : array_map(fn(array $position) => [(float) $position[0], (float) $position[1]], $coords),
            ];
        }

        return array_values($features);
    }

    private function normalizeFeatures(array $features): array
    {
        return array_map(function (array $feature) {
            $coordinates = $feature['type'] === 'point'
                ? array_map(fn($coordinate) => round((float) $coordinate, 7), $feature['coords'])
                : array_map(
                    fn(array $position) => array_map(fn($coordinate) => round((float) $coordinate, 7), $position),
                    $feature['coords']
                );

            return ['type' => $feature['type'], 'coords' => $coordinates];
        }, $features);
    }
}
