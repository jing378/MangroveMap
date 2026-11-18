<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\DelineationRemoved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteAllNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_deletes_all_their_own_notifications_only(): void
    {
        $user = User::factory()->create(['role' => 'end_user']);
        $other = User::factory()->create(['role' => 'end_user']);
        foreach (range(1, 3) as $i) {
            $user->notify(new DelineationRemoved("Patch {$i}", 'pending'));
        }
        $user->notifications()->first()->markAsRead();
        $other->notify(new DelineationRemoved('Theirs', 'pending'));

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('data-total="3"', false);

        $this->actingAs($user)
            ->deleteJson(route('notifications.destroy-all'))
            ->assertOk()
            ->assertJson(['success' => true, 'deleted' => 3, 'unreadCount' => 0]);

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('data-total=', false)
            ->assertSee('No notifications yet');
    }

    public function test_guests_cannot_delete_notifications(): void
    {
        $this->deleteJson(route('notifications.destroy-all'))->assertUnauthorized();
    }
}
