<?php

namespace App\Services;

use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Support\ExtentOverlap;
use Illuminate\Support\Facades\Storage;

/**
 * How well the AI's mangrove areas matched what people accepted: for each drone survey, the areas exactly as
 * the AI drew them (ai_extent_original.json, or ai_extent.json while never edited) against the reviewed
 * boundary. The reference is the best one the survey has:
 *   - expert_approved: its delineation, approved by an expert (including later expert boundary edits);
 *   - submitted:       its delineation, sent by the owner and awaiting an expert;
 *   - owner_corrected: the owner's saved corrections in the review editor, not submitted yet.
 * Rejected delineations, and surveys whose AI draft was edited before drafts were kept, are left out.
 *
 * Scores are IoU, precision (share of the AI's area that was kept), recall (share of the final area the AI
 * found) and F1, per survey and pooled (from the summed areas, so large surveys weigh more).
 */
class ExtentAccuracyReport
{
    public const REFERENCES = [
        'expert_approved' => 'Expert-approved delineation',
        'submitted' => 'Submitted, awaiting expert',
        'owner_corrected' => 'Owner-corrected draft',
    ];

    public const MODELS = [
        'unet' => 'U-Net',
        'yolo' => 'YOLO canopy model',
        'roboflow' => 'Roboflow workflow',
    ];

    /**
     * @return array<int, array{layer_id: int, layer: string, owner: ?string, model: string, reference: string, reference_label: string, delineation_id: ?int, edited: bool, predicted_ha: float, reference_ha: float, overlap_ha: float, union_ha: float, iou: ?float, precision: ?float, recall: ?float, f1: ?float}>
     */
    public function cases(?int $layerId = null): array
    {
        $layers = DroneImageryLayer::query()
            ->with(['user:id,name', 'delineation'])
            ->where('segmentation_status', 'ready')
            ->when($layerId !== null, fn ($query) => $query->whereKey($layerId))
            ->orderBy('id')
            ->get();

        $cases = [];
        foreach ($layers as $layer) {
            $case = $this->caseFor($layer);
            if ($case !== null) {
                $cases[] = $case;
            }
        }

        return $cases;
    }

    /**
     * @param  array<int, array>|null  $cases  from cases(); computed when null
     * @return array{count: int, by_reference: array<string, int>, by_model: array<string, int>, pooled: array, mean: array{iou: ?float, precision: ?float, recall: ?float, f1: ?float}, unchanged: int}
     */
    public function summary(?array $cases = null): array
    {
        $cases ??= $this->cases();
        $count = count($cases);

        $mean = [];
        foreach (['iou', 'precision', 'recall', 'f1'] as $score) {
            $values = array_values(array_filter(array_column($cases, $score), fn ($value) => $value !== null));
            $mean[$score] = $values === [] ? null : round(array_sum($values) / count($values), 4);
        }

        return [
            'count' => $count,
            'by_reference' => array_count_values(array_column($cases, 'reference')),
            'by_model' => array_count_values(array_column($cases, 'model')),
            'pooled' => ExtentOverlap::scores(
                array_sum(array_column($cases, 'predicted_ha')),
                array_sum(array_column($cases, 'reference_ha')),
                array_sum(array_column($cases, 'overlap_ha')),
            ),
            'mean' => $mean,
            // Surveys whose AI areas were accepted without any change.
            'unchanged' => count(array_filter($cases, fn (array $case) => ! $case['edited'] && $case['reference'] !== 'owner_corrected')),
        ];
    }

    private function caseFor(DroneImageryLayer $layer): ?array
    {
        $disk = Storage::disk('local');
        $currentPath = "drone-imagery/processed/{$layer->id}/ai_extent.json";
        $current = $disk->exists($currentPath) ? json_decode((string) $disk->get($currentPath), true) : null;
        $current = is_array($current) ? $current : null;

        // The AI's own areas: the kept copy, else the current file while nobody has edited it.
        $original = $disk->exists($layer->aiOriginalExtentPath())
            ? json_decode((string) $disk->get($layer->aiOriginalExtentPath()), true)
            : null;
        if (! is_array($original)) {
            $original = $current !== null && empty($current['stats']['edited']) ? $current : null;
        }
        if ($original === null) {
            return null;
        }

        $delineation = $layer->delineation;
        if ($delineation instanceof Delineation && ! $delineation->is_rejected) {
            $reference = $delineation->is_approved ? 'expert_approved' : 'submitted';
            $referenceFeatures = is_array($delineation->features) ? $delineation->features : [];
            // The delineation sits where the imagery was finally placed.
            $referenceOffset = $layer->applied_edits['offset'] ?? $current['stats']['offset'] ?? null;
        } elseif ($delineation === null && $current !== null && ! empty($current['stats']['edited'])) {
            $reference = 'owner_corrected';
            $referenceFeatures = (array) ($current['features'] ?? []);
            $referenceOffset = $current['stats']['offset'] ?? null;
        } else {
            return null; // not reviewed yet, or rejected
        }

        $predicted = ExtentOverlap::shapesFromFeatures(
            $this->shifted((array) ($original['features'] ?? []), $original['stats']['offset'] ?? null, $referenceOffset, $layer)
        );
        $referenceShapes = ExtentOverlap::shapesFromFeatures($referenceFeatures);
        if ($predicted === [] && $referenceShapes === []) {
            return null;
        }

        $model = (string) ($original['stats']['extent_model'] ?? 'unet');

        return [
            'layer_id' => $layer->id,
            'layer' => (string) $layer->name,
            'owner' => $layer->user?->name,
            'model' => $model,
            'model_label' => self::MODELS[$model] ?? $model,
            'reference' => $reference,
            'reference_label' => self::REFERENCES[$reference],
            'delineation_id' => $delineation?->id,
            // Whether the owner (or an expert) changed the AI's areas at all.
            'edited' => ! empty($current['stats']['edited']) || $this->differs($predicted, $referenceShapes),
            ...ExtentOverlap::compare($predicted, $referenceShapes),
        ];
    }

    /**
     * The AI areas moved from the imagery offset they were drawn at to the reference's (the owner's
     * "position nudge" moves all areas with the imagery; that is alignment, not a correction).
     */
    private function shifted(array $features, mixed $from, mixed $to, DroneImageryLayer $layer): array
    {
        $deltaEast = (float) ($to['east_m'] ?? 0) - (float) ($from['east_m'] ?? 0);
        $deltaNorth = (float) ($to['north_m'] ?? 0) - (float) ($from['north_m'] ?? 0);
        if (abs($deltaEast) < 0.001 && abs($deltaNorth) < 0.001) {
            return $features;
        }

        $bounds = $layer->preview_bounds ?? $layer->bounds ?? [];
        $latitude = (((float) ($bounds['south'] ?? 0)) + ((float) ($bounds['north'] ?? 0))) / 2;
        $deltaLat = $deltaNorth / ExtentOverlap::METRES_PER_DEGREE;
        $deltaLng = $deltaEast / (ExtentOverlap::METRES_PER_DEGREE * max(0.01, cos(deg2rad($latitude))));

        return array_map(fn ($feature) => is_array($feature) ? [
            ...$feature,
            'coords' => array_map(
                fn ($point) => is_array($point) ? [(float) $point[0] + $deltaLat, (float) $point[1] + $deltaLng] : $point,
                (array) ($feature['coords'] ?? []),
            ),
        ] : $feature, $features);
    }

    /** Whether two shape sets differ by more than rounding (a vertex moved more than about 1 cm). */
    private function differs(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return true;
        }
        $flatten = function (array $shapes): array {
            $points = [];
            foreach ($shapes as $shape) {
                foreach ($shape as $ring) {
                    foreach ($ring as $point) {
                        $points[] = $point;
                    }
                }
            }

            return $points;
        };
        $pa = $flatten($a);
        $pb = $flatten($b);
        if (count($pa) !== count($pb)) {
            return true;
        }
        foreach ($pa as $index => $point) {
            if (abs($point[0] - $pb[$index][0]) > 1e-7 || abs($point[1] - $pb[$index][1]) > 1e-7) {
                return true;
            }
        }

        return false;
    }
}
