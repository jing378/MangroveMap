<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderLogoLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_logo_links_to_each_roles_own_dashboard(): void
    {
        $cases = [
            ['end_user', 'dashboard'],
            ['expert', 'expert.dashboard'],
            ['admin', 'admin.dashboard'],
        ];

        foreach ($cases as [$role, $dashboard]) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('profile.show'))
                ->assertOk()
                ->assertSee('<a href="' . route($dashboard) . '" class="logo"', false);
        }
    }
}
