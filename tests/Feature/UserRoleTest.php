<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    private function updatePayload(User $user, string $role): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
        ];
    }

    public function test_sign_up_always_creates_a_resident_even_if_a_role_is_posted(): void
    {
        Event::fake([Registered::class]);

        $this->postJson('/register', [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms' => '1',
            'role' => 'admin',
        ])->assertCreated();

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_RESIDENT, $user->role);
        $this->assertSame('Resident', $user->roleLabel());
    }

    public function test_users_created_without_a_valid_role_become_residents(): void
    {
        $this->assertSame(User::ROLE_RESIDENT, User::factory()->create()->role);
        $this->assertSame(User::ROLE_RESIDENT, User::factory()->create(['role' => 'manager'])->role);
        $this->assertSame(User::ROLE_EXPERT, User::factory()->create(['role' => 'expert'])->role);
        $this->assertSame(User::ROLE_ADMIN, User::factory()->create(['role' => 'admin'])->role);
    }

    public function test_role_labels(): void
    {
        $this->assertSame('Resident', User::roleLabelFor('end_user'));
        $this->assertSame('Expert', User::roleLabelFor('expert'));
        $this->assertSame('Administrator', User::roleLabelFor('admin'));
        $this->assertSame('Resident', User::roleLabelFor(null));
    }

    public function test_profile_and_sidebar_show_the_correct_role_label(): void
    {
        foreach (['end_user' => 'Resident', 'expert' => 'Expert', 'admin' => 'Administrator'] as $role => $label) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('profile.show'))
                ->assertOk()
                ->assertSee($label)
                ->assertDontSee('End user');
        }
    }

    public function test_admin_users_page_labels_each_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'expert']);
        User::factory()->create(['role' => 'end_user']);

        $this->actingAs($admin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee('<span class="role-badge admin">Administrator</span>', false)
            ->assertSee('<span class="role-badge scientist">Expert</span>', false)
            ->assertSee('<span class="role-badge viewer">Resident</span>', false)
            ->assertDontSee('<option value="manager">', false);
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), $this->updatePayload($admin, 'end_user'))
            ->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_admin_can_demote_another_admin_while_one_remains(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $other), $this->updatePayload($other, 'expert'))
            ->assertSessionHasNoErrors();

        $this->assertSame('expert', $other->fresh()->role);
    }

    public function test_admin_can_promote_resident_to_expert(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $resident), $this->updatePayload($resident, 'expert'))
            ->assertSessionHasNoErrors();

        $this->assertSame('expert', $resident->fresh()->role);
    }

    public function test_residents_and_experts_cannot_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'end_user']))
            ->get(route('admin.users'))->assertRedirect(route('dashboard'));

        $this->actingAs(User::factory()->create(['role' => 'expert']))
            ->get(route('admin.users'))->assertRedirect(route('expert.dashboard'));
    }
}
