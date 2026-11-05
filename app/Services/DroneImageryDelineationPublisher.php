<?php

namespace App\Services;

use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Models\UserActivity;
use App\Notifications\DelineationSubmittedForReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a confirmed drone layer's mangrove areas (ai_extent.json, as corrected in the review editor) into
 * a delineation, once the imagery is tiled. Like a hand-drawn submission: a resident's goes to the experts
 * for review (the imagery is shared on the map once it is approved), an expert's is published at once.
 */
class DroneImageryDelineationPublisher
{
    /** Same cap as a hand-drawn delineation (EndUserController::storeDelineation). */
    public const MAX_AREAS = 20;

    public function __construct(private BoundaryResolver $boundaryResolver) {}

    /** @return array<int, array{type: string, coords: array}> the layer's mangrove areas */
    public static function areasOf(DroneImageryLayer $layer): array
    {
        $disk = Storage::disk('local');
        if (
            $layer->segmentation_status !== 'ready'
            || $layer->segmentation_path !== "drone-imagery/processed/{$layer->id}/ai_extent.json"
            || ! $disk->exists($layer->segmentation_path)
        ) {
            return [];
        }

        $result = json_decode((string) $disk->get($layer->segmentation_path), true);

        return array_values(array_filter(
            (array) ($result['features'] ?? []),
            fn ($feature) => is_array($feature)
                && ($feature['type'] ?? null) === 'area'
                && is_array($feature['coords'] ?? null)
                && count($feature['coords']) >= 3
        ));
    }

    public function publish(DroneImageryLayer $layer): ?Delineation
    {
        $details = $layer->delineation_details;
        if (! is_array($details) || $layer->delineation()->exists()) {
            return null;
        }

        // Details saved as a draft ("Save draft") may have no genus yet: only confirm submits them.
        $genera = array_values(array_filter((array) ($details['genera'] ?? []), fn ($genus) => is_string($genus) && trim($genus) !== ''));
        if ($genera === []) {
            return null;
        }

        $areas = array_slice(self::areasOf($layer), 0, self::MAX_AREAS);
        if ($areas === []) {
            $layer->update(['delineation_details' => null]);

            return null;
        }
        $features = $this->boundaryResolver->resolveFeatures(array_map(fn (array $area) => [
            'type' => 'area',
            'coords' => $area['coords'],
            'genera' => $genera,
        ], $areas));

        $owner = $layer->user;
        $isExpert = (bool) $owner?->isExpert();
        // Details are cleared with the insert, so a failure keeps them for another try. The layer row is
        // locked and re-checked: the job and a page load (DroneImageryController::index) may race to submit.
        $delineation = DB::transaction(function () use ($layer, $details, $features, $isExpert, $owner) {
            $locked = DroneImageryLayer::query()->lockForUpdate()->find($layer->id);
            if ($locked === null || $locked->delineation_details === null || $locked->delineation()->exists()) {
                return null;
            }
            $locked->update(['delineation_details' => null]);
            $layer->setRawAttributes($locked->getAttributes(), true);

            return Delineation::create([
                'user_id' => $layer->user_id,
                'drone_imagery_layer_id' => $layer->id,
                'name' => trim((string) ($details['name'] ?? '')) ?: $layer->name,
                'notes' => trim((string) ($details['notes'] ?? '')) ?: "Mangrove areas from drone survey \"{$layer->name}\".",
                'features' => $features,
                ...($isExpert ? ['is_approved' => true, 'approved_at' => now(), 'approved_by' => $owner->id] : []),
            ]);
        });
        if ($delineation === null) {
            return null; // submitted by the other caller in the meantime
        }

        if ($isExpert) {
            UserActivity::recordDelineationPublished($delineation, $owner);
        } else {
            UserActivity::recordDelineationSubmitted($delineation);
            User::where('role', 'expert')
                ->get()
                ->each(fn (User $expert) => $expert->notify(new DelineationSubmittedForReview($delineation)));
        }

        return $delineation;
    }
}
