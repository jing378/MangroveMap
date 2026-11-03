<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DelineationBoundaryEditTest extends TestCase
{
    use RefreshDatabase;

    private function residentDelineation(User $resident, array $attributes = []): Delineation
    {
        return Delineation::create(array_merge([
            'user_id' => $resident->id,
            'name' => 'Resident boundary',
            'notes' => 'Drawn by a resident',
            'features' => [[
                'type' => 'area',
                'coords' => [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01], [10.0, 124.0]],
                'genera' => ['Rhizophora'],
            ]],
        ], $attributes));
    }

    private function editedPayload(): array
    {
        return ['features' => [[
            'type' => 'area',
            // moved one vertex and added a new one
            'coords' => [[10.0, 124.0], [10.0, 124.02], [10.005, 124.025], [10.012, 124.01], [10.0, 124.0]],
            'genera' => ['Rhizophora'],
        ]]];
    }

    public function test_expert_edits_resident_delineation_in_place_and_keeps_owner_and_status(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->residentDelineation($resident, [
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $expert->id,
        ]);
        $originalFeatures = $delineation->features;

        $this->actingAs($expert)
            ->putJson(route('delineations.boundary.update', $delineation), $this->editedPayload())
            ->assertOk()
            ->assertJsonPath('delineation.id', $delineation->id)
            ->assertJsonPath('delineation.user_id', $resident->id)
            ->assertJsonPath('delineation.last_revised_by_name', $expert->name)
            ->assertJsonCount(5, 'delineation.features.0.coords');

        $this->assertSame(1, Delineation::count(), 'No duplicate delineation may be created.');
        $fresh = $delineation->fresh();
        $this->assertSame($resident->id, (int) $fresh->user_id);
        $this->assertTrue($fresh->is_approved);
        $this->assertSame('Resident boundary', $fresh->name);
        $this->assertSame('Drawn by a resident', $fresh->notes);
        $this->assertEquals([10.005, 124.025], $fresh->features[0]['coords'][2]);

        $revision = $fresh->revisions()->firstOrFail();
        $this->assertSame($expert->id, (int) $revision->revised_by);
        $this->assertEquals($originalFeatures, $revision->snapshot['features']);
    }

    public function test_resident_can_edit_own_pending_but_not_approved_or_others(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);
        $other = User::factory()->create(['role' => 'end_user']);
        $pending = $this->residentDelineation($owner);
        $approved = $this->residentDelineation($owner, ['is_approved' => true]);

        $this->actingAs($other)
            ->putJson(route('delineations.boundary.update', $pending), $this->editedPayload())
            ->assertForbidden();

        $this->actingAs($owner)
            ->putJson(route('delineations.boundary.update', $approved), $this->editedPayload())
            ->assertForbidden();

        $this->actingAs($owner)
            ->putJson(route('delineations.boundary.update', $pending), $this->editedPayload())
            ->assertOk();

        $this->assertFalse($pending->fresh()->is_approved);
        $this->assertSame(2, Delineation::count());
    }

    public function test_polygon_needs_three_distinct_points(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->residentDelineation($resident);

        $this->actingAs($expert)
            ->putJson(route('delineations.boundary.update', $delineation), ['features' => [[
                'type' => 'area',
                'coords' => [[10.0, 124.0], [10.0, 124.01], [10.0, 124.0]],
            ]]])
            ->assertStatus(422);

        $this->assertSame(0, $delineation->revisions()->count());
    }

    public function test_map_payload_exposes_last_editor_after_reload(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->residentDelineation($resident);

        $this->actingAs($expert)
            ->putJson(route('delineations.boundary.update', $delineation), $this->editedPayload())
            ->assertOk();

        $this->actingAs($resident);
        // buildDashboardData uses MySQL's YEAR(); provide it for the in-memory SQLite test DB.
        \Illuminate\Support\Facades\DB::connection()->getPdo()
            ->sqliteCreateFunction('YEAR', fn($date) => $date ? (int) substr($date, 0, 4) : null, 1);
        $data = app(\App\Http\Controllers\EndUserController::class)->buildDashboardData();
        $record = collect($data['delineations'])->firstWhere('id', $delineation->id);

        $this->assertSame($expert->name, $record['last_revised_by_name']);
        $this->assertArrayNotHasKey('latest_revision', $record);
        $this->assertEquals([10.005, 124.025], $record['features'][0]['coords'][2]);
    }

    public function test_resident_map_shows_own_delineation_details(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $this->residentDelineation($resident, [
            'is_approved' => true,
            'planting_recommendation' => 'Plant Rhizophora along the shoreline.',
        ]);
        DB::connection()->getPdo()
            ->sqliteCreateFunction('YEAR', fn($date) => $date ? (int) substr($date, 0, 4) : null, 1);

        $this->actingAs($resident)
            ->get(route('map'))
            ->assertOk()
            ->assertSee('Area (ha)')
            ->assertSee('Mangrove Genus Present')
            ->assertSee('Review status')
            ->assertSee('Planting recommendation')
            ->assertSee('Changes over time')
            ->assertSee('Annual observations')
            ->assertSee('Rhizophora')
            ->assertSee('Plant Rhizophora along the shoreline.');
    }

    public function test_resident_map_shows_approved_community_delineation_details(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);
        $residentViewer = User::factory()->create(['role' => 'end_user']);
        $this->residentDelineation($owner, [
            'is_approved' => true,
            'planting_recommendation' => 'Plant Avicennia in the intertidal zone.',
        ]);
        DB::connection()->getPdo()
            ->sqliteCreateFunction('YEAR', fn($date) => $date ? (int) substr($date, 0, 4) : null, 1);

        $this->actingAs($residentViewer)
            ->get(route('map'))
            ->assertOk()
            ->assertSee('Area (ha)')
            ->assertSee('Mangrove Genus Present')
            ->assertSee('Review status')
            ->assertSee('Planting recommendation')
            ->assertSee('Changes over time')
            ->assertSee('Annual observations')
            ->assertSee('Rhizophora')
            ->assertSee('Plant Avicennia in the intertidal zone.');
    }
}
