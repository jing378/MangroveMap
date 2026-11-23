<?php

namespace Tests\Feature;

use App\Http\Controllers\EndUserController;
use App\Models\Delineation;
use App\Models\DelineationAnnualObservation;
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

    public function test_expert_can_add_a_new_area_while_editing_and_record_it_as_an_observation(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->residentDelineation($resident, ['is_approved' => true, 'approved_at' => now()]);
        $original = $delineation->features[0];
        $newArea = [
            'type' => 'area',
            'coords' => [[10.02, 124.0], [10.02, 124.01], [10.03, 124.01], [10.02, 124.0]],
            'genera' => ['Rhizophora'],
        ];

        // "Draw a new area" on the map sends the original shape plus the new one.
        $this->actingAs($expert)
            ->putJson(route('delineations.boundary.update', $delineation), [
                'features' => [$original, $newArea],
                'record_as' => 'observation',
                'observation_date' => now()->toDateString(),
                'observation_source' => array_key_first(DelineationAnnualObservation::SOURCES),
            ])
            ->assertOk()
            ->assertJsonCount(2, 'delineation.features')
            ->assertJsonPath('observation_year', (int) now()->format('Y'));

        $fresh = $delineation->fresh();
        $this->assertCount(2, $fresh->features);
        $this->assertEquals($newArea['coords'], $fresh->features[1]['coords']);
        $this->assertSame(1, Delineation::count(), 'The area is added to the same record.');
        $this->assertSame(1, $fresh->revisions()->count());
        $this->assertSame(1, $fresh->annualObservations()->count());
    }

    public function test_the_map_offers_draw_and_remove_area_buttons_to_experts_and_residents(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'expert']))
            ->get(route('expert.map'))
            ->assertOk()
            ->assertSee('id="addBoundaryAreaBtn"', false)
            ->assertSee('id="removeBoundaryAreaBtn"', false);

        // Residents edit their own pending delineations, with the same draw / remove area tools.
        $this->actingAs(User::factory()->create(['role' => 'end_user']))
            ->get(route('delineate'))
            ->assertOk()
            ->assertSee('id="addBoundaryAreaBtn"', false)
            ->assertSee('id="removeBoundaryAreaBtn"', false)
            ->assertDontSee('id="boundaryRecordAs"', false); // recording observations stays expert-only
    }

    public function test_expert_can_remove_an_area_while_editing(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $kept = [
            'type' => 'area',
            'coords' => [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01], [10.0, 124.0]],
            'genera' => ['Rhizophora'],
        ];
        $removed = [
            'type' => 'area',
            'coords' => [[10.02, 124.0], [10.02, 124.01], [10.03, 124.01], [10.02, 124.0]],
            'genera' => ['Avicennia'],
        ];
        $delineation = $this->residentDelineation($resident, ['features' => [$kept, $removed], 'is_approved' => true]);

        // "Remove selected area" leaves that shape out of the list sent on save.
        $this->actingAs($expert)
            ->putJson(route('delineations.boundary.update', $delineation), ['features' => [$kept]])
            ->assertOk()
            ->assertJsonCount(1, 'delineation.features');

        $fresh = $delineation->fresh();
        $this->assertCount(1, $fresh->features);
        $this->assertEquals($kept['coords'], $fresh->features[0]['coords']);
        $this->assertTrue($fresh->is_approved);
        // The removed area stays in the revision history.
        $this->assertCount(2, $fresh->revisions()->firstOrFail()->snapshot['features']);

        // At least one area must stay.
        $this->actingAs($expert)
            ->putJson(route('delineations.boundary.update', $delineation), ['features' => []])
            ->assertStatus(422);
    }

    public function test_resident_can_add_an_area_to_their_own_pending_delineation(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $delineation = $this->residentDelineation($resident);
        $original = $delineation->features[0];
        $newArea = [
            'type' => 'area',
            'coords' => [[10.02, 124.0], [10.02, 124.01], [10.03, 124.01], [10.02, 124.0]],
            'genera' => ['Rhizophora'],
        ];

        $this->actingAs($resident)
            ->putJson(route('delineations.boundary.update', $delineation), ['features' => [$original, $newArea]])
            ->assertOk()
            ->assertJsonCount(2, 'delineation.features');

        $fresh = $delineation->fresh();
        $this->assertCount(2, $fresh->features);
        $this->assertFalse($fresh->is_approved); // still waits for an expert
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
        DB::connection()->getPdo()
            ->sqliteCreateFunction('YEAR', fn ($date) => $date ? (int) substr($date, 0, 4) : null, 1);
        $data = app(EndUserController::class)->buildDashboardData();
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
            ->sqliteCreateFunction('YEAR', fn ($date) => $date ? (int) substr($date, 0, 4) : null, 1);

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
            ->sqliteCreateFunction('YEAR', fn ($date) => $date ? (int) substr($date, 0, 4) : null, 1);

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
