<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContributorStatsTest extends TestCase
{
    use RefreshDatabase;

    private function delineation(User $user, array $attributes = []): Delineation
    {
        return Delineation::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Stand',
            'features' => [],
            'is_approved' => false,
            'is_rejected' => false,
        ], $attributes));
    }

    public function test_admin_dashboard_shows_contributor_counts_and_top_contributors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $alice = User::factory()->create(['role' => 'end_user', 'name' => 'Alice']);
        $bob = User::factory()->create(['role' => 'end_user', 'name' => 'Bob']);
        $carol = User::factory()->create(['role' => 'expert', 'name' => 'Carol']);

        $this->delineation($alice, ['is_approved' => true]);
        $this->delineation($alice, ['is_approved' => true]);
        $this->delineation($alice);
        $this->delineation($bob);
        $old = $this->delineation($carol, ['is_rejected' => true]);
        $old->forceFill(['created_at' => now()->subMonths(3)])->save();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertViewHas('totalContributors', 3)
            ->assertViewHas('approvedContributors', 1)
            ->assertViewHas('activeContributorsThisMonth', 2)
            ->assertSee('Delineation Contributors')
            ->assertSee('Bob')
            ->assertSee('Carol');

        $top = $response->viewData('topContributors')->first();
        $this->assertSame($alice->id, $top->user_id);
        $this->assertSame(3, (int) $top->total_count);
        $this->assertSame(2, (int) $top->approved_count);
        $this->assertSame(1, (int) $top->pending_count);
    }
}
