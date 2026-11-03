<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardContributorStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // buildDashboardData uses MySQL's YEAR(); provide it for the in-memory SQLite test DB.
        DB::connection()->getPdo()
            ->sqliteCreateFunction('YEAR', fn($date) => $date ? (int) substr($date, 0, 4) : null, 1);
    }

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

    public static function dashboards(): array
    {
        return [
            'resident' => ['end_user', 'dashboard'],
            'expert' => ['expert', 'expert.dashboard'],
        ];
    }

    #[DataProvider('dashboards')]
    public function test_dashboard_shows_community_contributors(string $role, string $route): void
    {
        $viewer = User::factory()->create(['role' => $role, 'name' => 'Viewer']);
        $alice = User::factory()->create(['role' => 'end_user', 'name' => 'Alice']);
        $bob = User::factory()->create(['role' => 'end_user', 'name' => 'Bob']);

        $approved = ['is_approved' => true, 'approved_at' => now()];
        $this->delineation($alice, $approved);
        $this->delineation($alice, $approved);
        $this->delineation($viewer, $approved);
        // Pending/rejected work is not counted for non-admin viewers.
        $this->delineation($bob);
        $this->delineation($bob, ['is_rejected' => true]);

        $response = $this->actingAs($viewer)->get(route($route));

        $response->assertOk()
            ->assertViewHas('communityContributors', 2)
            ->assertViewHas('activeContributorsThisMonth', 2)
            ->assertViewHas('myContributorRank', 2)
            ->assertSee('Community Contributors')
            ->assertSee('#2 of 2')
            ->assertSeeInOrder(['Top Contributors', 'Alice', 'Viewer']);

        $this->assertSame(
            [$alice->id, $viewer->id],
            $response->viewData('topContributors')->pluck('user_id')->all()
        );
    }

    public function test_user_without_approved_work_has_no_rank(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $this->delineation($resident);

        $this->actingAs($resident)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('communityContributors', 0)
            ->assertViewHas('myContributorRank', null)
            ->assertSee('Get a delineation approved to join')
            ->assertSee('Be the first contributor!');
    }
}
