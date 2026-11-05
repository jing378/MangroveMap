<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The mobile hamburger shows a red dot for unread notifications (the sidebar badge is hidden on mobile). */
class MobileMenuNotificationBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function giveUnreadNotifications(User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\TestNotification',
                'data' => ['message' => "Notification {$i}"],
            ]);
        }
    }

    public function test_hamburger_shows_a_dot_without_a_number_for_residents_and_experts(): void
    {
        foreach (['end_user', 'expert'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->giveUnreadNotifications($user, 3);

            $this->actingAs($user)->get(route('profile.show'))
                ->assertOk()
                ->assertSee('<span class="mobile-menu-badge" id="mobileMenuBadge" role="img"', false)
                // The count is only in the screen-reader label; the dot itself is empty.
                ->assertSee('aria-label="3 unread notifications"></span>', false);
        }
    }

    public function test_hamburger_dot_is_hidden_without_unread_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('id="mobileMenuBadge" role="img"  hidden', false);
    }

    public function test_admin_hamburger_shows_the_dot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->giveUnreadNotifications($admin, 2);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="2 unread notifications"></span>', false);
    }
}
