<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * map.delineations: what the map redraws from right after a save, edit, review or removal
 * (refreshMapData in workspace-scripts), with the same visibility rules as the page itself.
 */
class MapDelineationsRefreshTest extends TestCase
{
    use RefreshDatabase;

    private const AREA = [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01], [10.0, 124.0]];

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection()->getPdo()
            ->sqliteCreateFunction('YEAR', fn ($date) => $date ? (int) substr($date, 0, 4) : null, 1);
    }

    private function delineation(User $owner, array $attributes = []): Delineation
    {
        return Delineation::create(array_merge([
            'user_id' => $owner->id,
            'name' => 'Riverside stand',
            'notes' => 'Dense stand',
            'features' => [['type' => 'area', 'coords' => self::AREA, 'genera' => ['Avicennia']]],
        ], $attributes));
    }

    public function test_resident_gets_own_and_approved_community_delineations(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $other = User::factory()->create(['role' => 'end_user']);
        $own = $this->delineation($resident, ['name' => 'Mine, pending']);
        $approved = $this->delineation($other, ['name' => 'Approved', 'is_approved' => true, 'approved_at' => now()]);
        $this->delineation($other, ['name' => 'Someone else pending']);

        $this->actingAs($resident)
            ->getJson(route('map.delineations'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'delineations')
            ->assertJsonPath('delineations.0.id', $own->id)
            ->assertJsonCount(1, 'approvedDelineationsForMap')
            ->assertJsonPath('approvedDelineationsForMap.0.id', $approved->id)
            ->assertJsonCount(0, 'residentDelineationsForMap');
    }

    public function test_expert_also_gets_resident_submissions_and_sees_changes_right_away(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $resident = User::factory()->create(['role' => 'end_user']);
        $pending = $this->delineation($resident, ['name' => 'Pending stand']);

        $this->actingAs($expert)
            ->getJson(route('map.delineations'))
            ->assertOk()
            ->assertJsonPath('residentDelineationsForMap.0.id', $pending->id)
            ->assertJsonPath('residentDelineationsForMap.0.is_approved', false);

        // After a change (here an approval) the next refresh returns the new state.
        $pending->update(['is_approved' => true, 'approved_at' => now(), 'approved_by' => $expert->id]);
        $this->actingAs($expert)
            ->getJson(route('map.delineations'))
            ->assertOk()
            ->assertJsonPath('residentDelineationsForMap.0.is_approved', true);
    }

    public function test_guests_cannot_read_the_map_data(): void
    {
        $this->getJson(route('map.delineations'))->assertUnauthorized();
    }
}
