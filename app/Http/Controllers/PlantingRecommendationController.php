<?php

namespace App\Http\Controllers;

use App\Models\Delineation;
use App\Models\PlantingRecommendation;
use App\Services\BoundaryResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Expert planting recommendations: areas drawn (or selected from approved delineations) on the
 * map and evaluated for mangrove planting. Stored separately from resident delineations.
 */
class PlantingRecommendationController extends Controller
{
    private const MAP_QUERY_LIMIT = 300;

    public function index(Request $request)
    {
        $user = $request->user();

        $recommendations = PlantingRecommendation::query()
            ->with(['user:id,name', 'delineation:id,name'])
            ->latest()
            ->limit(self::MAP_QUERY_LIMIT)
            ->get()
            ->map(fn(PlantingRecommendation $recommendation) => $this->payload($recommendation, $user->id))
            ->values();

        // Approved delineations an expert can select as the area to evaluate (polygons only).
        $referenceAreas = Delineation::fetchForMap(
            Delineation::approved()->with('user:id,name'),
            Delineation::MAP_QUERY_LIMIT
        )
            ->map(fn(Delineation $delineation) => [
                'id' => $delineation->id,
                'name' => $delineation->name ?: "Delineation {$delineation->id}",
                'created_by' => $delineation->user?->name,
                'features' => collect(EndUserController::sanitizeFeaturesForMap($delineation->features))
                    ->where('type', 'area')
                    ->values()
                    ->all(),
            ])
            ->filter(fn(array $area) => $area['features'] !== [])
            ->values();

        return view('users.recommendations', [
            'page' => 'recommendations',
            'recommendations' => $recommendations,
            'referenceAreas' => $referenceAreas,
            'suitabilityLevels' => PlantingRecommendation::SUITABILITY_LEVELS,
            'recommendedGenera' => PlantingRecommendation::RECOMMENDED_GENERA,
        ]);
    }

    public function store(Request $request, BoundaryResolver $boundaryResolver): JsonResponse
    {
        $data = $this->validated($request, $boundaryResolver);

        $recommendation = $request->user()->plantingRecommendations()->create($data);
        $recommendation->load(['user:id,name', 'delineation:id,name']);

        return response()->json([
            'message' => 'Planting recommendation saved.',
            'recommendation' => $this->payload($recommendation, $request->user()->id),
        ], 201);
    }

    public function update(Request $request, PlantingRecommendation $recommendation, BoundaryResolver $boundaryResolver): JsonResponse
    {
        $this->authorizeOwner($request, $recommendation);

        $recommendation->update($this->validated($request, $boundaryResolver));
        $recommendation->load(['user:id,name', 'delineation:id,name']);

        return response()->json([
            'message' => 'Planting recommendation updated.',
            'recommendation' => $this->payload($recommendation, $request->user()->id),
        ]);
    }

    public function destroy(Request $request, PlantingRecommendation $recommendation): JsonResponse
    {
        $this->authorizeOwner($request, $recommendation);

        $recommendation->delete();

        return response()->json(['message' => 'Planting recommendation deleted.']);
    }

    private function authorizeOwner(Request $request, PlantingRecommendation $recommendation): void
    {
        abort_unless(
            (int) $recommendation->user_id === (int) $request->user()->id,
            403,
            'Only the expert who created this recommendation can change it.'
        );
    }

    /** @return array<string, mixed> */
    private function payload(PlantingRecommendation $recommendation, int $viewerId): array
    {
        return array_merge($recommendation->toMapPayload(), [
            'can_edit' => (int) $recommendation->user_id === $viewerId,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, BoundaryResolver $boundaryResolver): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'delineation_id' => ['nullable', 'integer', Rule::exists('delineations', 'id')->where('is_approved', true)->whereNull('hidden_at')],
            'features' => ['required', 'array', 'min:1', 'max:20'],
            'features.*.type' => ['required', 'string', 'in:area'],
            'features.*.coords' => ['required', 'array', 'min:3', 'max:10000'],
            'suitability' => ['required', 'string', Rule::in(array_keys(PlantingRecommendation::SUITABILITY_LEVELS))],
            // A "not suitable" verdict needs no genus; every other verdict names what to plant.
            'recommended_genera' => ['present', 'array', 'required_unless:suitability,not_suitable'],
            'recommended_genera.*' => ['string', 'distinct', Rule::in(PlantingRecommendation::RECOMMENDED_GENERA)],
            'recommendation' => ['nullable', 'string', 'max:5000'],
            'site_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'recommended_genera.required_unless' => 'Select at least one recommended genus, or mark the area as not suitable.',
        ]);

        $features = [];
        foreach ($data['features'] as $index => $feature) {
            $coords = collect($feature['coords']);
            $valid = $coords->every(fn($pair) => is_array($pair)
                && count($pair) === 2
                && is_numeric($pair[0] ?? null) && is_numeric($pair[1] ?? null)
                && abs((float) $pair[0]) <= 90 && abs((float) $pair[1]) <= 180);
            $coords = $valid ? $coords->map(fn($pair) => [(float) $pair[0], (float) $pair[1]])->values()->all() : [];

            if (! $valid || collect($coords)->map(fn($pair) => $pair[0] . ',' . $pair[1])->unique()->count() < 3) {
                throw ValidationException::withMessages([
                    "features.$index.coords" => 'An area needs at least 3 distinct valid points.',
                ]);
            }

            $features[] = ['type' => 'area', 'coords' => $coords];
        }

        return [
            'name' => trim($data['name']),
            'delineation_id' => $data['delineation_id'] ?? null,
            'features' => $boundaryResolver->resolveFeatures($features),
            'suitability' => $data['suitability'],
            'recommended_genera' => $data['suitability'] === 'not_suitable' ? [] : array_values($data['recommended_genera']),
            'recommendation' => isset($data['recommendation']) ? (trim($data['recommendation']) ?: null) : null,
            'site_notes' => isset($data['site_notes']) ? (trim($data['site_notes']) ?: null) : null,
        ];
    }
}
