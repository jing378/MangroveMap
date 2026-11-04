<?php

namespace Tests\Feature;

use App\Jobs\FinalizeDroneImagery;
use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Notifications\DelineationSubmittedForReview;
use App\Services\DroneImageryDelineationPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Drone uploads by role. A resident confirms and submits: the mangrove areas become a pending delineation
 * and the imagery stays private (experts can see it to review). Once an expert approves the delineation,
 * the imagery and the delineation are on everyone's map. An expert's confirm publishes both at once.
 */
class DroneImageryRoleFlowTest extends TestCase
{
    use RefreshDatabase;

    private const AREA = [[9.95, 124.05], [9.95, 124.06], [9.96, 124.06], [9.96, 124.05]];

    private function layer(User $owner, string $status = 'ready', array $attributes = []): DroneImageryLayer
    {
        $layer = DroneImageryLayer::create([
            'user_id' => $owner->id,
            'name' => "{$owner->role} survey",
            'status' => $status,
            'source_type' => 'single',
            'bounds' => ['west' => 124.0, 'south' => 9.9, 'east' => 124.1, 'north' => 10.0],
            'preview_path' => 'preview.png',
            'preview_bounds' => ['west' => 124.0, 'south' => 9.9, 'east' => 124.1, 'north' => 10.0],
            'zoom_min' => 12,
            'zoom_max' => 20,
            ...$attributes,
        ]);
        $layer->update(['tiles_path' => "drone-imagery/tiles/{$layer->id}"]);

        return $layer->fresh();
    }

    /** The reviewed mangrove areas, as the editor saves them. */
    private function withAreas(DroneImageryLayer $layer, int $count = 1): DroneImageryLayer
    {
        $path = "drone-imagery/processed/{$layer->id}/ai_extent.json";
        Storage::disk('local')->put($path, json_encode([
            'features' => array_fill(0, $count, ['type' => 'area', 'coords' => self::AREA]),
        ]));
        $layer->update(['segmentation_status' => 'ready', 'segmentation_path' => $path]);

        return $layer->fresh();
    }

    private function submittedDelineation(DroneImageryLayer $layer, array $attributes = []): Delineation
    {
        return Delineation::create([
            'user_id' => $layer->user_id,
            'drone_imagery_layer_id' => $layer->id,
            'name' => $layer->name,
            'notes' => 'From drone imagery',
            'features' => [['type' => 'area', 'coords' => self::AREA, 'genera' => ['Rhizophora']]],
            ...$attributes,
        ]);
    }

    private function layersFor(User $viewer)
    {
        return collect($this->actingAs($viewer)->getJson(route('drone-imagery.index'))->assertOk()->json('layers'))
            ->keyBy('id');
    }

    public function test_first_confirm_requires_the_genus_and_stores_the_delineation_details(): void
    {
        Storage::fake('local');
        Queue::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $layer = $this->withAreas($this->layer($resident, 'awaiting_review'));
        $edits = ['mode' => 'remove', 'polygons' => [], 'offset' => ['east_m' => 0, 'north_m' => 0], 'opacity' => 0.8];

        $this->actingAs($resident)
            ->postJson(route('drone-imagery.confirm', $layer->id), $edits)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delineation');
        $this->assertSame('awaiting_review', $layer->fresh()->status);

        $this->actingAs($resident)
            ->postJson(route('drone-imagery.confirm', $layer->id), [
                ...$edits,
                'delineation' => ['name' => 'Cabalian mangroves', 'genera' => ['Rhizophora', ' Avicennia '], 'notes' => ''],
            ])
            ->assertAccepted();

        $layer->refresh();
        $this->assertSame('finalizing', $layer->status);
        $this->assertSame(['name' => 'Cabalian mangroves', 'genera' => ['Rhizophora', 'Avicennia'], 'notes' => null], $layer->delineation_details);
        Queue::assertPushed(FinalizeDroneImagery::class);
    }

    public function test_save_draft_keeps_the_delineation_details_and_the_editor_reopens_with_them(): void
    {
        Storage::fake('local');
        Queue::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $layer = $this->withAreas($this->layer($resident, 'awaiting_review'));
        $edits = ['mode' => 'remove', 'polygons' => [], 'offset' => ['east_m' => 0, 'north_m' => 0], 'opacity' => 0.8];

        // The genus may still be empty in a draft.
        $this->actingAs($resident)
            ->putJson(route('drone-imagery.edits.update', $layer->id), [
                ...$edits,
                'delineation' => ['name' => ' Cabalian mangroves ', 'genera' => [], 'notes' => 'Near the river mouth'],
            ])
            ->assertOk();
        $this->assertSame(
            ['name' => 'Cabalian mangroves', 'genera' => [], 'notes' => 'Near the river mouth'],
            $layer->fresh()->delineation_details
        );

        $this->actingAs($resident)
            ->putJson(route('drone-imagery.edits.update', $layer->id), [
                ...$edits,
                'delineation' => ['name' => 'Cabalian mangroves', 'genera' => ['Rhizophora', 'Lumnitzera'], 'notes' => ''],
            ])
            ->assertOk();
        $layer->refresh();
        $this->assertSame(['Rhizophora', 'Lumnitzera'], $layer->delineation_details['genera']);
        $this->assertSame('awaiting_review', $layer->status, 'Saving a draft does not submit anything.');
        Queue::assertNothingPushed();
        $this->assertSame(0, Delineation::count());

        $this->actingAs($resident)->get(route('drone-imagery.edit', $layer->id))
            ->assertOk()
            ->assertSee('"delineation_draft":{"name":"Cabalian mangroves","genera":["Rhizophora","Lumnitzera"],"notes":null}', false);
    }

    public function test_a_draft_without_a_genus_is_never_turned_into_a_delineation(): void
    {
        Storage::fake('local');
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $layer = $this->withAreas($this->layer($resident, 'ready', [
            'delineation_details' => ['name' => 'Draft only', 'genera' => [], 'notes' => null],
        ]));

        $this->assertNull(app(DroneImageryDelineationPublisher::class)->publish($layer));
        $this->assertSame(0, Delineation::count());
        $this->assertSame('Draft only', $layer->fresh()->delineation_details['name']);
    }

    public function test_confirm_refuses_more_areas_than_a_delineation_holds(): void
    {
        Storage::fake('local');
        Queue::fake();
        $expert = User::factory()->create(['role' => 'expert']);
        $layer = $this->withAreas($this->layer($expert, 'awaiting_review'), DroneImageryDelineationPublisher::MAX_AREAS + 1);

        $this->actingAs($expert)
            ->postJson(route('drone-imagery.confirm', $layer->id), [
                'mode' => 'remove', 'polygons' => [], 'offset' => ['east_m' => 0, 'north_m' => 0], 'opacity' => 0.8,
                'delineation' => ['genera' => ['Rhizophora']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('areas');
        Queue::assertNothingPushed();
    }

    public function test_resident_submission_becomes_a_pending_delineation_and_notifies_experts(): void
    {
        Storage::fake('local');
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $layer = $this->withAreas($this->layer($resident, 'ready', [
            'delineation_details' => ['name' => 'Cabalian mangroves', 'genera' => ['Rhizophora'], 'notes' => null],
        ]));

        $delineation = app(DroneImageryDelineationPublisher::class)->publish($layer);

        $this->assertNotNull($delineation);
        $this->assertSame($layer->id, $delineation->drone_imagery_layer_id);
        $this->assertSame('Cabalian mangroves', $delineation->name);
        $this->assertSame(['Rhizophora'], $delineation->features[0]['genera']);
        $this->assertFalse((bool) $delineation->is_approved);
        $this->assertNull($layer->fresh()->delineation_details);
        Notification::assertSentTo($expert, DelineationSubmittedForReview::class);

        // Running again (e.g. a later re-confirm) does not submit a second delineation.
        $this->assertNull(app(DroneImageryDelineationPublisher::class)->publish($layer->fresh()));
        $this->assertSame(1, Delineation::count());
    }

    public function test_expert_confirm_publishes_the_delineation_with_the_imagery(): void
    {
        Storage::fake('local');
        Notification::fake();
        $expert = User::factory()->create(['role' => 'expert']);
        $layer = $this->withAreas($this->layer($expert, 'ready', [
            'delineation_details' => ['name' => null, 'genera' => ['Avicennia'], 'notes' => null],
        ]));

        $delineation = app(DroneImageryDelineationPublisher::class)->publish($layer);

        $this->assertTrue((bool) $delineation->is_approved);
        $this->assertSame($expert->id, $delineation->approved_by);
        $this->assertSame($layer->name, $delineation->name);
        Notification::assertNothingSent();
        $this->assertTrue($this->layersFor($expert)[$layer->id]['auto_show']);
    }

    public function test_resident_imagery_reaches_the_map_only_after_expert_approval(): void
    {
        Storage::fake('local');
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $otherResident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $layer = $this->layer($resident);
        Storage::disk('local')->put("{$layer->tiles_path}/12/3458/1933.png", 'tile');
        $delineation = $this->submittedDelineation($layer);
        $tile = route('drone-imagery.tile', [$layer->id, 12, 3458, 1933]);

        // Pending: the owner sees it in their list (not on the map), experts see it to review, others not at all.
        $own = $this->layersFor($resident)[$layer->id];
        $this->assertSame('pending', $own['review_state']);
        $this->assertFalse($own['auto_show']);
        $this->assertArrayNotHasKey($layer->id, $this->layersFor($otherResident)->all());
        $this->actingAs($otherResident)->get($tile)->assertNotFound();
        $this->assertTrue($this->layersFor($expert)[$layer->id]['auto_show']);
        $this->actingAs($expert)->get($tile)->assertOk();

        $this->actingAs($expert)
            ->postJson(route('expert.delineations.approve', $delineation->id))
            ->assertOk();

        // Approved: imagery and delineation are on everyone's map.
        foreach ([$resident, $otherResident, $expert] as $viewer) {
            $listed = $this->layersFor($viewer)[$layer->id];
            $this->assertTrue($listed['is_published']);
            $this->assertTrue($listed['auto_show']);
        }
        $this->actingAs($otherResident)->get($tile)->assertOk();
        $this->assertTrue(Delineation::approved()->whereKey($delineation->id)->exists());
    }

    public function test_rejected_resident_imagery_stays_private(): void
    {
        Storage::fake('local');
        $resident = User::factory()->create(['role' => 'end_user']);
        $otherResident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $layer = $this->layer($resident);
        $this->submittedDelineation($layer, ['is_rejected' => true, 'rejected_by' => $expert->id, 'rejection_notes' => 'Includes the fishpond.']);

        $own = $this->layersFor($resident)[$layer->id];
        $this->assertSame('rejected', $own['review_state']);
        $this->assertSame('Includes the fishpond.', $own['rejection_notes']);
        $this->assertNotNull($own['delineation_url']);
        $this->assertFalse($own['auto_show']);
        $this->assertArrayNotHasKey($layer->id, $this->layersFor($otherResident)->all());

        // Experts still see it in their uploads list (with the feedback), but it is not put on their map.
        $forExpert = $this->layersFor($expert)[$layer->id];
        $this->assertSame('Includes the fishpond.', $forExpert['rejection_notes']);
        $this->assertFalse($forExpert['auto_show']);
    }

    public function test_experts_see_every_upload_with_the_uploaders_name(): void
    {
        Storage::fake('local');
        $expert = User::factory()->create(['role' => 'expert']);
        $otherExpert = User::factory()->create(['role' => 'expert', 'name' => 'Expert Two']);
        $resident = User::factory()->create(['role' => 'end_user', 'name' => 'Resident One']);
        $residentDraft = $this->layer($resident, 'awaiting_review');
        $residentPending = $this->layer($resident);
        $this->submittedDelineation($residentPending);
        $otherExpertProcessing = $this->layer($otherExpert, 'processing');

        $layers = $this->layersFor($expert);
        foreach ([$residentDraft, $residentPending] as $layer) {
            $this->assertSame('Resident One', $layers[$layer->id]['uploaded_by']);
            $this->assertSame('end_user', $layers[$layer->id]['uploader_role']);
            $this->assertFalse($layers[$layer->id]['is_owner']);
        }
        $this->assertSame('Expert Two', $layers[$otherExpertProcessing->id]['uploaded_by']);
        $this->assertSame('expert', $layers[$otherExpertProcessing->id]['uploader_role']);
        // A submitted delineation can be opened for review; only the pending one goes on the map.
        $this->assertNotNull($layers[$residentPending->id]['delineation_url']);
        $this->assertTrue($layers[$residentPending->id]['auto_show']);
        $this->assertFalse($layers[$residentDraft->id]['auto_show']);

        // Residents still only see their own uploads and published imagery.
        $this->assertArrayNotHasKey($otherExpertProcessing->id, $this->layersFor($resident)->all());

        $this->actingAs($expert)->get(route('drone-imagery.page'))
            ->assertOk()
            ->assertSee('Uploads by residents and other experts')
            ->assertSee('"shows_others":true', false);
        $this->actingAs($resident)->get(route('drone-imagery.page'))
            ->assertOk()
            ->assertDontSee('Uploads by residents and other experts');
    }

    public function test_editor_asks_for_delineation_details_only_on_the_first_confirm(): void
    {
        Storage::fake('local');
        Queue::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $layer = $this->layer($resident, 'awaiting_review');

        $this->actingAs($resident)->get(route('drone-imagery.edit', $layer->id))
            ->assertOk()
            ->assertSee('Confirm &amp; submit for review', false)
            ->assertSee('id="droneEditorGenera"', false);

        $this->submittedDelineation($layer);
        $this->actingAs($resident)->get(route('drone-imagery.edit', $layer->id))
            ->assertOk()
            ->assertDontSee('id="droneEditorGenera"', false)
            ->assertSee('Confirm &amp; apply', false);

        $expert = User::factory()->create(['role' => 'expert']);
        $this->actingAs($expert)->get(route('drone-imagery.edit', $this->layer($expert, 'awaiting_review')->id))
            ->assertOk()
            ->assertSee('Confirm &amp; publish', false);
    }
}
