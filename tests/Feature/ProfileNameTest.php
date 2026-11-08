<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProfileNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_name_keeps_compound_surnames_together(): void
    {
        $this->assertSame(['Juan', 'Dela Cruz'], User::splitName('Juan Dela Cruz'));
        $this->assertSame(['Maria Clara', 'Santos'], User::splitName('Maria Clara Santos'));
        $this->assertSame(['Jose', 'de los Reyes'], User::splitName('Jose de los Reyes'));
        $this->assertSame(['Ana', 'Reyes'], User::splitName('  Ana   Reyes '));
        $this->assertSame(['Cher', ''], User::splitName('Cher'));
    }

    public function test_display_name_uses_middle_initial_and_suffix(): void
    {
        $this->assertSame('Juan D. Dela Cruz Jr.', User::displayName('Juan', 'dela Paz', 'Dela Cruz', 'jr'));
        $this->assertSame('Juan Dela Cruz', User::displayName('Juan', null, 'Dela Cruz', ''));
        $this->assertSame('Ana Reyes III', User::displayName('Ana', '  ', 'Reyes', 'iii'));
        $this->assertSame('Ñora É. Santos', User::displayName('Ñora', 'élan', 'Santos', null));
    }

    public function test_parse_name_detects_middle_initial_and_suffix(): void
    {
        $this->assertSame(
            ['first_name' => 'Juan', 'middle_name' => 'D', 'last_name' => 'Dela Cruz', 'suffix' => 'Jr.'],
            User::parseName('Juan D. Dela Cruz Jr.'),
        );
        $this->assertSame(
            ['first_name' => 'Maria', 'middle_name' => 'Y', 'last_name' => 'Santos', 'suffix' => null],
            User::parseName('Maria Y. Santos'),
        );
        $this->assertSame(
            ['first_name' => 'Demario', 'middle_name' => null, 'last_name' => 'Sawayn', 'suffix' => 'V'],
            User::parseName('Demario Sawayn V'),
        );
        $this->assertSame(
            ['first_name' => 'Maria Clara', 'middle_name' => null, 'last_name' => 'Santos', 'suffix' => null],
            User::parseName('Maria Clara Santos'),
        );
    }

    public function test_register_page_has_optional_middle_name_and_suffix(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('id="middle_name" name="middle_name"', false)
            ->assertSee('Leave empty if no middle name.')
            ->assertSee('id="suffix" name="suffix"', false)
            ->assertSee('Leave empty if no suffix.')
            ->assertSee('(Optional)');
    }

    public function test_registration_with_middle_name_and_suffix(): void
    {
        Event::fake([Registered::class]);

        $this->postJson('/register', [
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'suffix' => 'jr',
            'email' => 'juan@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms' => '1',
        ])->assertCreated();

        $user = User::where('email', 'juan@example.com')->firstOrFail();
        $this->assertSame('Santos', $user->middle_name);
        $this->assertSame('Jr.', $user->suffix);
        $this->assertSame('Juan S. Dela Cruz Jr.', $user->name);
    }

    public function test_registration_rejects_invalid_suffix(): void
    {
        $this->postJson('/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'suffix' => 'Jr3!',
            'email' => 'juan@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms' => '1',
        ])->assertStatus(422)->assertJsonValidationErrors('suffix');
    }

    public function test_profile_shows_middle_initial_and_editable_full_middle_name(): void
    {
        $user = User::factory()->create(['first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Dela Cruz', 'suffix' => 'Jr.']);

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('<h2 class="sidebar-name">Juan S. Dela Cruz Jr.</h2>', false)
            ->assertSee('value="Santos"', false)
            ->assertSee('value="Jr."', false);
    }

    public function test_profile_update_sets_and_clears_middle_name_and_suffix(): void
    {
        $user = User::factory()->create(['name' => 'Juan Dela Cruz']);
        $this->actingAs($user);
        $payload = ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => $user->email, 'phone' => '09123456789'];

        $this->putJson(route('profile.update'), $payload + ['middle_name' => 'Santos', 'suffix' => 'Sr.'])
            ->assertOk()
            ->assertJsonPath('name', 'Juan S. Dela Cruz Sr.');

        $this->putJson(route('profile.update'), $payload + ['middle_name' => '', 'suffix' => ''])
            ->assertOk()
            ->assertJsonPath('name', 'Juan Dela Cruz');

        $user->refresh();
        $this->assertNull($user->middle_name);
        $this->assertNull($user->suffix);
    }

    public function test_registration_stores_first_and_last_name(): void
    {
        Event::fake([Registered::class]);

        $this->postJson('/register', [
            'first_name' => 'Maria Clara',
            'last_name' => 'Dela Cruz',
            'email' => 'maria@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms' => '1',
        ])->assertCreated();

        $user = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('Maria Clara', $user->first_name);
        $this->assertSame('Dela Cruz', $user->last_name);
        $this->assertSame('Maria Clara Dela Cruz', $user->name);
    }

    public function test_profile_page_shows_first_and_last_name_fields(): void
    {
        $user = User::factory()->create(['name' => 'Juan Dela Cruz']);

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('First Name')
            ->assertSee('Last Name')
            ->assertSee('id="first_name" name="first_name" class="form-control"', false)
            ->assertSee('value="Juan"', false)
            ->assertSee('value="Dela Cruz"', false)
            ->assertDontSee('Full Name');
    }

    public function test_updating_profile_saves_names_and_rebuilds_display_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'role' => 'end_user']);

        $this->actingAs($user)->putJson(route('profile.update'), [
            'first_name' => '  Maria Clara ',
            'last_name' => 'Santos',
            'email' => $user->email,
            'phone' => '09123456789',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('Maria Clara', $user->first_name);
        $this->assertSame('Santos', $user->last_name);
        $this->assertSame('Maria Clara Santos', $user->name);
    }

    public function test_first_and_last_name_are_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson(route('profile.update'), [
            'first_name' => ' ',
            'last_name' => '',
            'email' => $user->email,
            'phone' => '09123456789',
        ])->assertStatus(422)
            ->assertJsonPath('errors.first_name.0', 'First name is required.')
            ->assertJsonPath('errors.last_name.0', 'Last name is required.');
    }

    public function test_admin_editing_full_name_keeps_first_and_last_in_sync(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['name' => 'Ana Reyes']);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Pedro San Juan',
            'email' => $user->email,
            'role' => 'end_user',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Pedro', $user->first_name);
        $this->assertSame('San Juan', $user->last_name);
    }

    public function test_admin_edit_modal_saves_name_parts_like_the_profile_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['name' => 'Ana Reyes']);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $user), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('name="first_name"', false)
            ->assertSee('name="suffix"', false)
            ->assertDontSee('name="name"', false);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'first_name' => ' Juan ',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'suffix' => 'jr',
            'email' => $user->email,
            'role' => 'end_user',
            'phone' => '09123456789',
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $user->refresh();
        $this->assertSame('Juan', $user->first_name);
        $this->assertSame('Dela Cruz', $user->last_name);
        $this->assertSame('Juan S. Dela Cruz Jr.', $user->name);
    }

    public function test_admin_edit_uses_signup_password_and_phone_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $this->actingAs($admin)->putJson(route('admin.users.update', $user), [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => $user->email,
            'role' => 'end_user',
            'phone' => '12345678901',
            'password' => 'alllowercase1',
            'password_confirmation' => 'alllowercase1',
        ])->assertStatus(422)->assertJsonValidationErrors(['phone', 'password']);
    }
}
