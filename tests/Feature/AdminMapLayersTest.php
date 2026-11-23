<?php

namespace Tests\Feature;

use App\Jobs\ProcessDroneImagery;
use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Models\UserActivity;
use App\Notifications\DelineationModerated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMapLayersTest extends TestCase
{
    use RefreshDatabase;

    private const AREA = [['type' => 'area', 'coords' => [[10.1, 125.1], [10.1, 125.2], [10.2, 125.2]], 'genera' => ['Rhizophora']]];

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function delineation(array $overrides = []): Delineation
    {
        $owner = $overrides['user_id'] ?? User::factory()->create(['role' => 'end_user'])->id;

        return Delineation::forceCreate(array_merge([
            'user_id' => $owner,
            'name' => 'Sogod Bay Stand',
            'notes' => 'Dense stand near the river mouth.',
            'features' => self::AREA,
            'is_approved' => true,
            'approved_at' => now(),
        ], $overrides));
    }

    private function imageryLayer(array $overrides = []): DroneImageryLayer
    {
        return DroneImageryLayer::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'expert'])->id,
            'name' => 'Maasin survey',
            'status' => 'ready',
            'source_type' => 'single',
            'bounds' => ['south' => 10.1, 'west' => 125.1, 'north' => 10.2, 'east' => 125.2],
            'zoom_min' => 12,
            'zoom_max' => 20,
        ], $overrides));
    }

    public function test_admin_sees_delineations_and_imagery_tabs(): void
    {
        $admin = $this->admin();
        $this->delineation(['name' => 'Published Bay']);
        $this->delineation(['name' => 'Waiting Bay', 'is_approved' => false, 'approved_at' => null]);
        $this->imageryLayer(['name' => 'Expert Survey']);

        $this->actingAs($admin)
            ->get(route('admin.map-layers.index'))
            ->assertOk()
            ->assertSee('Published Bay')
            ->assertSee('Waiting Bay')
            ->assertSee('Pending review')
            ->assertSee('data-features', false)
            ->assertDontSee('Expert Survey');

        $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['tab' => 'imagery']))
            ->assertOk()
            ->assertSee('Expert Survey')
            ->assertSee('data-tile-url', false)
            ->assertDontSee('Published Bay');
    }

    public function test_delineation_status_filter(): void
    {
        $admin = $this->admin();
        $this->delineation(['name' => 'Published Bay']);
        $this->delineation(['name' => 'Waiting Bay', 'is_approved' => false, 'approved_at' => null]);

        $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['status' => 'pending']))
            ->assertSee('Waiting Bay')
            ->assertDontSee('Published Bay');

        // There is no "hidden" status any more: an unknown value is ignored and every row is listed.
        $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['status' => 'hidden']))
            ->assertSee('Waiting Bay')
            ->assertSee('Published Bay');
    }

    public function test_rejected_delineations_show_the_revision_days_left_or_that_they_are_removable(): void
    {
        $admin = $this->admin();
        $rejected = ['is_approved' => false, 'approved_at' => null, 'is_rejected' => true];
        $this->delineation(['name' => 'Fresh Rejection', 'rejected_at' => now()->subDays(4)->subHour()] + $rejected);
        $this->delineation(['name' => 'Last Day', 'rejected_at' => now()->subDays(6)->subHours(20)] + $rejected);
        $this->delineation(['name' => 'Old Rejection', 'rejected_at' => now()->subDays(8)] + $rejected);

        $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['status' => 'rejected']))
            ->assertOk()
            ->assertSee('3 days left to revise')
            ->assertSee('1 day left to revise')
            ->assertSee('Revision period over · removable')
            ->assertSee('data-rejected', false); // the preview draws these in red

        // Published delineations keep the green preview.
        $this->delineation(['name' => 'Published Bay']);
        $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['status' => 'published']))
            ->assertSee('Published Bay')
            ->assertDontSee('data-rejected', false);
    }

    public function test_imagery_stage_filter_covers_every_dropdown_option(): void
    {
        $admin = $this->admin();
        $resident = fn () => User::factory()->create(['role' => 'end_user'])->id;
        $layers = [
            'processing' => $this->imageryLayer(['name' => 'Stage processing', 'status' => 'processing']),
            'detecting' => $this->imageryLayer(['name' => 'Stage detecting', 'status' => 'awaiting_review', 'segmentation_status' => 'processing']),
            'waiting_owner' => $this->imageryLayer(['name' => 'Stage waiting', 'status' => 'awaiting_review', 'segmentation_status' => 'ready', 'segmented_at' => now()->subDays(2)]),
            'finalizing' => $this->imageryLayer(['name' => 'Stage finalizing', 'status' => 'finalizing']),
            'pending_expert' => $this->imageryLayer(['name' => 'Stage pending', 'user_id' => $resident()]),
            'rejected' => $this->imageryLayer(['name' => 'Stage rejected', 'user_id' => $resident()]),
            'published' => $this->imageryLayer(['name' => 'Stage published']), // an expert's: published without review
            'not_submitted' => $this->imageryLayer(['name' => 'Stage unsubmitted', 'user_id' => $resident()]),
            'failed' => $this->imageryLayer(['name' => 'Stage failed', 'status' => 'failed']),
        ];
        $this->delineation(['is_approved' => false, 'approved_at' => null, 'drone_imagery_layer_id' => $layers['pending_expert']->id]);
        $this->delineation(['is_approved' => false, 'approved_at' => null, 'is_rejected' => true, 'rejected_at' => now(), 'drone_imagery_layer_id' => $layers['rejected']->id]);

        foreach ($layers as $stage => $layer) {
            $this->assertSame($stage, $layer->fresh()->stage(), $stage);

            $html = $this->actingAs($admin)
                ->get(route('admin.map-layers.index', ['tab' => 'imagery', 'status' => $stage]))
                ->assertOk()
                ->assertSee('<option value="'.$stage.'" selected', false)
                ->getContent();

            // Each stage lists exactly its own layer.
            foreach ($layers as $other) {
                $other->is($layer)
                    ? $this->assertStringContainsString($other->name, $html, $stage)
                    : $this->assertStringNotContainsString($other->name, $html, $stage.' lists '.$other->name);
            }
        }

        $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['tab' => 'imagery', 'status' => 'waiting_owner']))
            ->assertSee('AI draft ready 2 days ago');
    }

    public function test_stale_ai_drafts_are_counted_and_flagged(): void
    {
        $admin = $this->admin();
        $this->imageryLayer(['name' => 'Old draft', 'status' => 'awaiting_review', 'segmentation_status' => 'ready', 'segmented_at' => now()->subDays(9)]);
        $this->imageryLayer(['name' => 'New draft', 'status' => 'awaiting_review', 'segmentation_status' => 'ready', 'segmented_at' => now()->subDay()]);
        // The AI failed: still waiting for the owner, counted from the upload.
        $this->imageryLayer(['name' => 'No draft', 'status' => 'awaiting_review', 'segmentation_status' => 'failed'])
            ->forceFill(['created_at' => now()->subDays(8)])->save();

        $this->assertSame(2, DroneImageryLayer::staleOwnerDraft()->count());

        $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['tab' => 'imagery']))
            ->assertViewHas('counts', fn ($counts) => $counts['waitingOwner'] === 3 && $counts['staleDrafts'] === 2)
            ->assertSee('2 over 7 days', false)
            ->assertSee('No AI draft, processed', false)
            ->assertSee('status-stale', false);
    }

    public function test_pending_delineations_preview_their_outline_on_their_drone_imagery(): void
    {
        $admin = $this->admin();
        $ready = $this->imageryLayer(['name' => 'Confirmed survey']);
        $awaiting = $this->imageryLayer([
            'name' => 'Unconfirmed survey',
            'status' => 'awaiting_review',
            'bounds' => null,
            'preview_path' => 'drone-imagery/processed/x/preview.png',
            'preview_bounds' => ['south' => 10.3, 'west' => 125.3, 'north' => 10.4, 'east' => 125.4],
        ]);
        $pending = ['is_approved' => false, 'approved_at' => null];
        $this->delineation($pending + ['name' => 'Hand-drawn stand']);
        $this->delineation($pending + ['name' => 'Drone stand', 'drone_imagery_layer_id' => $ready->id]);
        $this->delineation($pending + ['name' => 'Unconfirmed stand', 'drone_imagery_layer_id' => $awaiting->id]);

        $html = $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['status' => 'pending']))
            ->assertOk()
            ->getContent();

        // Every pending delineation can be previewed (its outline), and the drone ones also on their imagery.
        $this->assertSame(3, substr_count($html, 'data-kind="delineation"'));
        $this->assertSame(3, substr_count($html, "data-features='[{\"type\":\"area\""));
        $this->assertStringContainsString('data-tile-url="/drone-imagery/'.$ready->id.'/tiles/{z}/{x}/{y}"', $html);
        $this->assertStringContainsString('data-preview-url="/drone-imagery/'.$awaiting->id.'/preview"', $html);
    }

    public function test_drone_layers_preview_with_their_submitted_delineation(): void
    {
        $admin = $this->admin();
        $withDelineation = $this->imageryLayer(['name' => 'Submitted survey', 'segmentation_status' => 'ready']);
        $this->delineation(['name' => 'Survey stand', 'is_approved' => false, 'approved_at' => null, 'drone_imagery_layer_id' => $withDelineation->id]);
        $aiOnly = $this->imageryLayer(['name' => 'Unsubmitted survey', 'segmentation_status' => 'ready']);

        $html = $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['tab' => 'imagery']))
            ->assertOk()
            ->getContent();

        // The submitted one carries its delineation (pending) instead of the AI extent; the other keeps the AI extent.
        $this->assertSame(1, substr_count($html, "data-features='[{\"type\":\"area\""));
        $this->assertStringContainsString('data-delineation-status="Pending review"', $html);
        $this->assertStringNotContainsString('data-segmentation-url="/drone-imagery/'.$withDelineation->id.'/segmentation"', $html);
        $this->assertStringContainsString('data-segmentation-url="/drone-imagery/'.$aiOnly->id.'/segmentation"', $html);
    }

    public function test_layers_awaiting_owner_review_preview_their_review_image_for_admins_only(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $layer = $this->imageryLayer([
            'name' => 'Waiting Survey',
            'status' => 'awaiting_review',
            'bounds' => null,
            'preview_bounds' => ['south' => 10.1, 'west' => 125.1, 'north' => 10.2, 'east' => 125.2],
        ]);
        $layer->update(['preview_path' => "drone-imagery/processed/{$layer->id}/preview.png"]);
        Storage::disk('local')->put($layer->preview_path, 'png');

        $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['tab' => 'imagery']))
            ->assertSee('data-preview-url="/drone-imagery/'.$layer->id.'/preview"', false)
            ->assertDontSee('data-tile-url', false);

        $this->get(route('drone-imagery.preview', $layer->id))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'end_user']))
            ->get(route('drone-imagery.preview', $layer->id))
            ->assertNotFound();
    }

    public function test_delineations_are_labelled_and_filtered_by_source(): void
    {
        $admin = $this->admin();
        $layer = $this->imageryLayer(['name' => 'Bontoc survey']);
        $this->delineation(['name' => 'Traced stand']);
        $this->delineation(['name' => 'Surveyed stand', 'source' => 'drone', 'drone_imagery_layer_id' => $layer->id]);
        // Its imagery was deleted: still a drone survey, not "hand-drawn".
        $this->delineation(['name' => 'Orphaned stand', 'source' => 'drone']);

        $this->actingAs($admin)
            ->get(route('admin.map-layers.index'))
            ->assertSee('<option value="drone"', false)
            ->assertSeeInOrder(['Orphaned stand', 'Drone survey (AI-assisted)', 'imagery deleted'], false)
            ->assertSeeInOrder(['Surveyed stand', 'Drone survey (AI-assisted)', 'Updated'], false)
            ->assertSeeInOrder(['Traced stand', 'Hand-drawn'], false);

        $this->get(route('admin.map-layers.index', ['source' => 'drone']))
            ->assertSee('Surveyed stand')->assertSee('Orphaned stand')->assertDontSee('Traced stand');
        $this->get(route('admin.map-layers.index', ['source' => 'manual']))
            ->assertSee('Traced stand')->assertDontSee('Surveyed stand')->assertDontSee('Orphaned stand');

        // Exports carry it too.
        $csv = $this->get(route('admin.map-layers.export', ['source' => 'drone']))->streamedContent();
        $this->assertStringContainsString('ID,Name,Status,Source,Owner', $csv);
        $this->assertStringContainsString('"Surveyed stand",Published,"Drone survey (AI-assisted)"', $csv);
        $this->assertStringNotContainsString('Traced stand', $csv);
        $geojson = json_decode($this->get(route('admin.map-layers.export', ['format' => 'geojson', 'source' => 'manual']))->streamedContent(), true);
        $this->assertSame(['manual'], array_unique(array_column(array_column($geojson['features'], 'properties'), 'source')));
    }

    public function test_delineations_whose_imagery_an_admin_deleted_are_marked_as_drone(): void
    {
        $admin = $this->admin();
        $fromDrone = $this->delineation(['name' => 'maac_r001_c001', 'notes' => 'test1']);
        $traced = $this->delineation(['name' => 'Traced later']);
        $layer = $this->imageryLayer(['name' => 'maac_r001_c001']);
        UserActivity::record($admin, 'Deleted drone imagery: maac_r001_c001', 'Map Layers', 'success', $layer);

        (require database_path('migrations/2026_11_23_000003_mark_drone_delineations_from_deleted_imagery.php'))->up();

        $this->assertSame('drone', $fromDrone->fresh()->source);
        $this->assertSame('manual', $traced->fresh()->source);
    }

    public function test_hand_drawn_delineations_are_saved_as_manual(): void
    {
        $this->assertSame('manual', (new Delineation)->source);
    }

    public function test_imagery_preview_urls_are_relative(): void
    {
        $admin = $this->admin();
        $layer = $this->imageryLayer(['segmentation_status' => 'ready']);

        $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['tab' => 'imagery']))
            ->assertSee('data-tile-url="/drone-imagery/'.$layer->id.'/tiles/{z}/{x}/{y}"', false)
            ->assertSee('data-segmentation-url="/drone-imagery/'.$layer->id.'/segmentation"', false);
    }

    public function test_delineation_rows_preview_on_click_and_only_offer_delete(): void
    {
        $admin = $this->admin();
        $delineation = $this->delineation();

        $html = $this->actingAs($admin)
            ->get(route('admin.map-layers.index'))
            ->assertOk()
            ->getContent();

        // The row itself carries the preview (no eye button), and Delete is its only action.
        $this->assertMatchesRegularExpression('/<tr class="layer-row" data-layer-id="d'.$delineation->id.'"\s+data-preview tabindex="0"/', $html);
        $this->assertStringNotContainsString('title="Preview on map"', $html);
        $this->assertStringNotContainsString('data-edit-delineation', $html);
        $this->assertStringNotContainsString('data-hide-delineation', $html);
        $this->assertStringContainsString(route('admin.map-layers.delineations.destroy', $delineation), $html);

        $this->assertFalse(Route::has('admin.map-layers.delineations.update'));
        $this->assertFalse(Route::has('admin.map-layers.delineations.hide'));
        $this->assertFalse(Route::has('admin.map-layers.delineations.restore'));
    }

    public function test_imagery_rows_preview_on_click_and_only_offer_retry_and_delete(): void
    {
        $admin = $this->admin();
        $ready = $this->imageryLayer(['name' => 'Ready survey', 'segmentation_status' => 'ready']);
        $failed = $this->imageryLayer(['name' => 'Broken survey', 'status' => 'failed', 'bounds' => null]);

        $html = $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['tab' => 'imagery']))
            ->assertOk()
            ->getContent();

        // The ready row carries its preview; the failed one has nothing to show, so it is not clickable.
        $this->assertMatchesRegularExpression('/<tr class="layer-row" data-layer-id="i'.$ready->id.'"\s+data-preview tabindex="0"/', $html);
        $this->assertMatchesRegularExpression('/<tr class="layer-row" data-layer-id="i'.$failed->id.'"\s*>/', $html);
        $this->assertStringNotContainsString('title="Preview on map"', $html);
        $this->assertStringNotContainsString('data-rename-imagery', $html);
        $this->assertStringNotContainsString('AI segmentation"><i class="bi bi-cpu"', $html);
        $this->assertStringContainsString(route('admin.map-layers.imagery.reprocess', $failed), $html);
        $this->assertStringNotContainsString(route('admin.map-layers.imagery.reprocess', $ready), $html);
        $this->assertStringContainsString(route('admin.map-layers.imagery.destroy', $ready), $html);

        $this->assertFalse(Route::has('admin.map-layers.imagery.update'));
        $this->assertFalse(Route::has('admin.map-layers.imagery.segment'));
    }

    public function test_results_and_errors_use_the_shared_toast(): void
    {
        $admin = $this->admin();
        $delineation = $this->delineation();
        $ready = $this->imageryLayer();

        $html = $this->actingAs($admin)
            ->followingRedirects()
            ->delete(route('admin.map-layers.delineations.destroy', $delineation))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('window.AppActions?.notify("', $html);
        $this->assertStringNotContainsString('page-alert', $html);

        $html = $this->actingAs($admin)
            ->followingRedirects()
            ->from(route('admin.map-layers.index', ['tab' => 'imagery']))
            ->post(route('admin.map-layers.imagery.reprocess', $ready))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/window\.AppActions\?\.notify\(".+", \'error\'\)/', $html);
    }

    public function test_admin_deletes_any_delineation_and_owner_is_notified(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $delineation = $this->delineation();
        $owner = $delineation->user;

        $this->actingAs($admin)
            ->delete(route('admin.map-layers.delineations.destroy', $delineation))
            ->assertSessionHas('success');

        $this->assertModelMissing($delineation);
        Notification::assertSentTo($owner, DelineationModerated::class, fn ($n) => $n->action === 'deleted');
        $this->assertDatabaseHas('user_activities', ['activity' => 'Deleted delineation: Sogod Bay Stand']);
    }

    public function test_admin_deletes_another_users_imagery(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $layer = $this->imageryLayer();
        Storage::disk('local')->put("drone-imagery/tiles/{$layer->id}/12/0/0.png", 'tile');
        Storage::disk('local')->put("drone-imagery/uploads/{$layer->id}/a.tif", 'tif');

        $this->actingAs($admin)
            ->delete(route('admin.map-layers.imagery.destroy', $layer))
            ->assertSessionHas('success');

        $this->assertModelMissing($layer);
        Storage::disk('local')->assertMissing("drone-imagery/tiles/{$layer->id}/12/0/0.png");
        Storage::disk('local')->assertMissing("drone-imagery/uploads/{$layer->id}/a.tif");
    }

    public function test_deleting_a_drone_survey_deletes_its_delineation_too(): void
    {
        Storage::fake('local');
        Notification::fake();
        $admin = $this->admin();
        $layer = $this->imageryLayer(['name' => 'Linked survey']);
        $delineation = $this->delineation(['name' => 'Linked stand', 'source' => 'drone', 'is_approved' => false, 'approved_at' => null, 'drone_imagery_layer_id' => $layer->id]);
        $lone = $this->imageryLayer(['name' => 'Lone survey']);
        $traced = $this->delineation(['name' => 'Traced stand']);

        $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['tab' => 'imagery']))
            ->assertSee('Its delineation &quot;Linked stand&quot; is deleted with it, and the owner is notified.', false);

        // No drone delineation is left without its imagery.
        $this->delete(route('admin.map-layers.imagery.destroy', $layer))
            ->assertSessionHas('success', 'Drone survey "Linked survey" and its files were deleted, with its delineation "Linked stand".');

        $this->assertModelMissing($layer);
        $this->assertModelMissing($delineation);
        $this->assertModelExists($traced);
        Notification::assertSentTo($delineation->user, DelineationModerated::class, fn ($n) => $n->action === 'deleted');
        $this->assertDatabaseHas('user_activities', ['activity' => 'Deleted delineation: Linked stand']);

        $this->delete(route('admin.map-layers.imagery.destroy', $lone))
            ->assertSessionHas('success', 'Drone survey "Lone survey" and its files were deleted.');
    }

    public function test_delineations_and_their_drone_imagery_link_to_each_other(): void
    {
        $admin = $this->admin();
        $layer = $this->imageryLayer(['name' => 'Linked survey']);
        $delineation = $this->delineation(['name' => 'Linked stand', 'is_approved' => false, 'approved_at' => null, 'drone_imagery_layer_id' => $layer->id]);
        $this->delineation(['name' => 'Hand-drawn stand']);
        $this->imageryLayer(['name' => 'Lone survey']);

        // Each row links to the other tab, filtered to the linked record.
        $this->actingAs($admin)->get(route('admin.map-layers.index'))
            ->assertSee(route('admin.map-layers.index', ['tab' => 'imagery', 'layer' => $layer->id]));
        $this->get(route('admin.map-layers.index', ['tab' => 'imagery']))
            ->assertSee(route('admin.map-layers.index', ['delineation' => $delineation->id]), false)
            ->assertSee('No delineation yet');

        $this->get(route('admin.map-layers.index', ['delineation' => $delineation->id]))
            ->assertSee('Linked stand')->assertDontSee('Hand-drawn stand');

        // The delineation row also shows its drone imagery's stage when it says more than the row's own
        // status, so the admin need not switch tabs (Pending expert review would only repeat "Pending review").
        $this->get(route('admin.map-layers.index', ['delineation' => $delineation->id]))
            ->assertDontSee('Stage of the drone imagery', false);
        $layer->update(['status' => 'finalizing']);
        $this->get(route('admin.map-layers.index', ['delineation' => $delineation->id]))
            ->assertSee('title="Stage of the drone imagery">Finalizing</span>', false);
        $this->get(route('admin.map-layers.index', ['status' => 'published']))
            ->assertSee('Hand-drawn stand')->assertDontSee('Stage of the drone imagery', false);
        $this->get(route('admin.map-layers.index', ['tab' => 'imagery', 'layer' => $layer->id]))
            ->assertSee('Linked survey')->assertDontSee('Lone survey');
    }

    public function test_imagery_still_processing_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $layer = $this->imageryLayer(['status' => 'processing']);

        $this->actingAs($admin)
            ->delete(route('admin.map-layers.imagery.destroy', $layer))
            ->assertSessionHas('error');

        $this->assertModelExists($layer);
    }

    public function test_admin_retries_failed_processing_only(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $failed = $this->imageryLayer(['status' => 'failed', 'error_message' => 'gdalwarp crashed']);
        $ready = $this->imageryLayer(['name' => 'Ready survey']);

        $this->actingAs($admin)->post(route('admin.map-layers.imagery.reprocess', $failed))->assertSessionHas('success');
        $this->assertSame('queued', $failed->fresh()->status);
        $this->assertNull($failed->fresh()->error_message);
        Queue::assertPushed(ProcessDroneImagery::class, fn ($job) => $job->layerId === $failed->id);

        // A ready layer cannot be "reprocessed".
        $this->actingAs($admin)->post(route('admin.map-layers.imagery.reprocess', $ready))->assertSessionHas('error');
        $this->assertSame('ready', $ready->fresh()->status);
    }

    public function test_admin_can_preview_tiles_of_another_users_layer(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $layer = $this->imageryLayer();
        $layer->update(['tiles_path' => "drone-imagery/tiles/{$layer->id}"]);

        $this->actingAs($admin)
            ->get(route('drone-imagery.tile', ['layerId' => $layer->id, 'z' => 12, 'x' => 0, 'y' => 0]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_non_admins_cannot_manage_map_layers(): void
    {
        $delineation = $this->delineation();
        $layer = $this->imageryLayer();

        foreach (['end_user', 'expert'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.map-layers.index'))->assertRedirect($user->homeRoute());
            $this->actingAs($user)->delete(route('admin.map-layers.delineations.destroy', $delineation))->assertRedirect($user->homeRoute());
            $this->actingAs($user)->delete(route('admin.map-layers.imagery.destroy', $layer))->assertRedirect($user->homeRoute());
        }

        $this->assertModelExists($delineation);
        $this->assertModelExists($layer);
    }
}
