<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_record_captures_request_ip_and_user_agent(): void
    {
        $user = User::factory()->create(['role' => 'end_user', 'password' => 'Secret123']);

        $this->withHeader('User-Agent', 'TestBrowser/1.0')
            ->post('/login', ['email' => $user->email, 'password' => 'Secret123']);

        $activity = UserActivity::where('user_id', $user->id)->where('activity', 'Logged in')->firstOrFail();
        $this->assertSame('127.0.0.1', $activity->ip_address);
        $this->assertSame('TestBrowser/1.0', $activity->user_agent);
    }

    public function test_admin_sees_activity_log_list_newest_first(): void
    {
        $admin = $this->admin();
        $resident = User::factory()->create(['name' => 'Rosa Resident', 'role' => 'end_user']);
        UserActivity::record($resident, 'Older entry', 'Map Delineation', 'pending', null, now()->subDay());
        UserActivity::record($resident, 'Newer entry', 'Classification');

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSeeInOrder(['Newer entry', 'Older entry'])
            ->assertSee('Rosa Resident');
    }

    public function test_activity_log_filters(): void
    {
        $admin = $this->admin();
        $resident = User::factory()->create(['name' => 'Rosa Resident', 'role' => 'end_user']);
        $expert = User::factory()->create(['name' => 'Eli Expert', 'role' => 'expert']);
        UserActivity::record($resident, 'Submitted delineation: Bay A', 'Map Delineation', 'pending');
        UserActivity::record($expert, 'Rejected delineation: Bay B', 'Expert Review', 'rejected');
        UserActivity::record($resident, 'Ran mangrove segmentation', 'Classification', 'success', null, now()->subDays(10));

        $this->actingAs($admin);

        $this->get(route('admin.activity-logs.index', ['module' => 'Expert Review']))
            ->assertSee('Bay B')->assertDontSee('Bay A');
        $this->get(route('admin.activity-logs.index', ['status' => 'pending']))
            ->assertSee('Bay A')->assertDontSee('Bay B');
        $this->get(route('admin.activity-logs.index', ['role' => 'expert']))
            ->assertSee('Bay B')->assertDontSee('Bay A');
        $this->get(route('admin.activity-logs.index', ['search' => 'eli expert']))
            ->assertSee('Bay B')->assertDontSee('Bay A');
        $this->get(route('admin.activity-logs.index', ['user' => $resident->id]))
            ->assertSee('Bay A')->assertDontSee('Bay B')->assertSee('Showing activity by');
        $this->get(route('admin.activity-logs.index', ['from' => now()->subDays(2)->format('Y-m-d')]))
            ->assertSee('Bay A')->assertDontSee('Ran mangrove segmentation');
        $this->get(route('admin.activity-logs.index', ['to' => now()->subDays(5)->format('Y-m-d')]))
            ->assertSee('Ran mangrove segmentation')->assertDontSee('Bay A');
    }

    public function test_invalid_filters_are_ignored(): void
    {
        $admin = $this->admin();
        UserActivity::record($admin, 'Logged in', 'Authentication');

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.index', ['status' => 'bogus', 'from' => 'not-a-date']))
            ->assertOk()
            ->assertSee('Logged in');
    }

    public function test_log_details_show_request_info_and_related_delineation(): void
    {
        $admin = $this->admin();
        $resident = User::factory()->create(['name' => 'Rosa Resident', 'role' => 'end_user']);
        $delineation = Delineation::create([
            'user_id' => $resident->id,
            'name' => 'Sogod Bay Stand',
            'features' => [],
            'is_approved' => false,
            'is_rejected' => true,
            'rejection_notes' => 'Boundary crosses the road.',
        ]);
        $activity = UserActivity::record($resident, 'Submitted delineation: Sogod Bay Stand', 'Map Delineation', 'pending', $delineation);
        $activity->forceFill(['ip_address' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0 Test'])->save();
        UserActivity::record($resident, 'Logged in', 'Authentication');

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.show', $activity))
            ->assertOk()
            ->assertSee('Activity #' . $activity->id)
            ->assertSee('203.0.113.7')
            ->assertSee('Mozilla/5.0 Test')
            ->assertSee('Rosa Resident')
            ->assertSee('Related Delineation')
            ->assertSee('Rejected')
            ->assertSee('Boundary crosses the road.')
            ->assertSee('Other Recent Activity by This User')
            ->assertSee('Logged in');
    }

    public function test_log_details_handle_deleted_subject(): void
    {
        $admin = $this->admin();
        $resident = User::factory()->create(['role' => 'end_user']);
        $delineation = Delineation::create(['user_id' => $resident->id, 'name' => 'Gone', 'features' => []]);
        $activity = UserActivity::record($resident, 'Submitted delineation: Gone', 'Map Delineation', 'pending', $delineation);
        $delineation->delete();

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.show', $activity))
            ->assertOk()
            ->assertSee('This delineation has since been deleted.');
    }

    public function test_non_admins_cannot_view_activity_logs(): void
    {
        $activity = UserActivity::record($this->admin(), 'Logged in', 'Authentication');

        foreach (['end_user', 'expert'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.activity-logs.index'))->assertRedirect($user->homeRoute());
            $this->actingAs($user)->get(route('admin.activity-logs.show', $activity))->assertRedirect($user->homeRoute());
        }
    }

    public function test_admin_sidebar_links_to_activity_logs(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.activity-logs.index'));
    }
}
