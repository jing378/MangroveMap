<?php

namespace App\Http\Controllers;

use App\Models\Delineation;
use App\Models\DelineationAnnualObservation;
use App\Models\Genus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DelineationAnnualObservationController extends Controller
{
    public function index(Request $request, Delineation $delineation): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin()
                || $user->isExpert()
                || $delineation->user_id === $user->id
                || $delineation->is_approved,
            404
        );

        $observations = $delineation->annualObservations()->orderBy('year')->get();
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
            'coverage' => $observations->map(fn(DelineationAnnualObservation $observation) => [
                'year' => $observation->year,
                'coverageAreaHa' => $observation->coverage_area_ha,
            ])->values(),
            'genusYear' => $latest?->year,
            'genusDistribution' => $genusDistribution,
        ]);
    }

    public function create(Request $request): View
    {
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
            ? DelineationAnnualObservation::findOrFail($request->integer('observation'))
            : null;

        return view('admin.annual-observations', compact(
            'delineations',
            'genera',
            'observations',
            'editingObservation'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'observation_id' => ['nullable', 'integer', 'exists:delineation_annual_observations,id'],
            'delineation_id' => ['required', 'integer', 'exists:delineations,id'],
            'year' => ['required', 'integer', 'between:2000,' . now()->year],
            'observation_date' => ['required', 'date'],
            'coverage_area_ha' => ['required', 'numeric', 'min:0'],
            'source' => ['required', 'string', 'max:255'],
            'genus_distribution' => ['nullable', 'array'],
            'genus_distribution.*' => ['nullable', 'numeric', 'between:0,100'],
        ]);

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

        $observation = isset($validated['observation_id'])
            ? DelineationAnnualObservation::findOrFail($validated['observation_id'])
            : new DelineationAnnualObservation();

        $duplicateYear = DelineationAnnualObservation::query()
            ->where('delineation_id', $validated['delineation_id'])
            ->where('year', $validated['year'])
            ->when($observation->exists, fn($query) => $query->where('id', '!=', $observation->id))
            ->exists();

        if ($duplicateYear) {
            return back()->withErrors([
                'year' => 'This delineation already has an observation for that year. Edit the existing record instead.',
            ])->withInput();
        }

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

        return redirect()->route('admin.annual-observations.create')
            ->with('success', 'Annual observation saved.');
    }
}
