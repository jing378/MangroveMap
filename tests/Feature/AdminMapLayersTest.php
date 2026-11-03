<?php

namespace Tests\Feature;

use App\Jobs\ProcessDroneImagery;
use App\Jobs\SegmentDroneImagery;
use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Notifications\DelineationModerated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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
        $this->delineation(['name' => 'Hidden Bay', 'hidden_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.map-layers.index', ['status' => 'hidden']))
            ->assertSee('Hidden Bay')
            ->assertDontSee('Published Bay');
    }

    public function test_admin_edits_delineation_details_and_keeps_a_revision(): void
    {
        $admin = $this->admin();
        $delineation = $this->delineation();

        $this->actingAs($admin)
            ->put(route('admin.map-layers.delineations.update', $delineation), [
                'name' => '  Sogod Bay North  ',
                'notes' => 'Renamed after field check.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $delineation->refresh();
        $this->assertSame('Sogod Bay North', $delineation->name);
        $this->assertSame('Renamed after field check.', $delineation->notes);
        $this->assertSame(self::AREA, $delineation->features);
        $this->assertTrue($delineation->is_approved);

        $revision = $delineation->revisions()->firstOrFail();
        $this->assertSame('admin_details_edit', $revision->snapshot['type']);
        $this->assertSame('Sogod Bay Stand', $revision->snapshot['name']);
        $this->assertSame($admin->id, $revision->revised_by);
        $this->assertDatabaseHas('user_activities', ['user_id' => $admin->id, 'module' => 'Map Layers']);
    }

    public function test_hiding_removes_delineation_from_published_maps_and_restoring_brings_it_back(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $delineation = $this->delineation();

        $this->actingAs($admin)
            ->post(route('admin.map-layers.delineations.hide', $delineation), ['reason' => 'Overlaps a fishpond.'])
            ->assertSessionHas('success');

        $delineation->refresh();
        $this->assertTrue($delineation->isHiddenFromMap());
        $this->assertSame('Overlaps a fishpond.', $delineation->hidden_reason);
        $this->assertSame(0, Delineation::approved()->count());
        Notification::assertSentTo(
            $delineation->user,
            DelineationModerated::class,
            fn($n) => $n->action === 'hidden' && $n->reason === 'Overlaps a fishpond.',
        );

        $this->actingAs($admin)
            ->post(route('admin.map-layers.delineations.restore', $delineation))
            ->assertSessionHas('success');

        $this->assertFalse($delineation->fresh()->isHiddenFromMap());
        $this->assertSame(1, Delineation::approved()->count());
        Notification::assertSentTo($delineation->user, DelineationModerated::class, fn($n) => $n->action === 'restored');
    }

    public function test_only_published_delineations_can_be_hidden(): void
    {
        $admin = $this->admin();
        $pending = $this->delineation(['is_approved' => false, 'approved_at' => null]);

        $this->actingAs($admin)
            ->post(route('admin.map-layers.delineations.hide', $pending))
            ->assertSessionHas('error');

        $this->assertFalse($pending->fresh()->isHiddenFromMap());
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
        Notification::assertSentTo($owner, DelineationModerated::class, fn($n) => $n->action === 'deleted');
        $this->assertDatabaseHas('user_activities', ['activity' => 'Deleted delineation: Sogod Bay Stand']);
    }

    public function test_admin_renames_and_deletes_another_users_imagery(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $layer = $this->imageryLayer();
        Storage::disk('local')->put("drone-imagery/tiles/{$layer->id}/12/0/0.png", 'tile');
        Storage::disk('local')->put("drone-imagery/uploads/{$layer->id}/a.tif", 'tif');

        $this->actingAs($admin)
            ->put(route('admin.map-layers.imagery.update', $layer), ['name' => 'Maasin survey 2026'])
            ->assertSessionHas('success');
        $this->assertSame('Maasin survey 2026', $layer->fresh()->name);

        $this->actingAs($admin)
            ->delete(route('admin.map-layers.imagery.destroy', $layer))
            ->assertSessionHas('success');

        $this->assertModelMissing($layer);
        Storage::disk('local')->assertMissing("drone-imagery/tiles/{$layer->id}/12/0/0.png");
        Storage::disk('local')->assertMissing("drone-imagery/uploads/{$layer->id}/a.tif");
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

    public function test_admin_retries_failed_processing_and_runs_segmentation(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $failed = $this->imageryLayer(['status' => 'failed', 'error_message' => 'gdalwarp crashed']);
        $ready = $this->imageryLayer(['name' => 'Ready survey']);

        $this->actingAs($admin)->post(route('admin.map-layers.imagery.reprocess', $failed))->assertSessionHas('success');
        $this->assertSame('queued', $failed->fresh()->status);
        $this->assertNull($failed->fresh()->error_message);
        Queue::assertPushed(ProcessDroneImagery::class, fn($job) => $job->layerId === $failed->id);

        $this->actingAs($admin)->post(route('admin.map-layers.imagery.segment', $ready))->assertSessionHas('success');
        $this->assertSame('queued', $ready->fresh()->segmentation_status);
        Queue::assertPushed(SegmentDroneImagery::class, fn($job) => $job->layerId === $ready->id);

        // A ready layer cannot be "reprocessed", and segmentation can't be queued twice.
        $this->actingAs($admin)->post(route('admin.map-layers.imagery.reprocess', $ready))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('admin.map-layers.imagery.segment', $ready))->assertSessionHas('error');
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
