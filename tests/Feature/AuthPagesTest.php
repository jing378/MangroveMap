<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_uses_the_same_strength_rules_as_sign_up(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword1')]);
        $token = Password::createToken($user);

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors(['password' => 'Password must include an uppercase letter, a lowercase letter, and a number.']);

        $this->assertTrue(Hash::check('OldPassword1', $user->fresh()->password));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPassword1', $user->fresh()->password));
    }

    public function test_reset_page_shows_an_invalid_or_expired_link_error(): void
    {
        $user = User::factory()->create();
        $resetUrl = route('password.reset', ['token' => 'not-a-real-token', 'email' => $user->email]);

        $this->from($resetUrl)
            ->followingRedirects()
            ->post(route('password.update'), [
                'token' => 'not-a-real-token',
                'email' => $user->email,
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ])
            ->assertOk()
            ->assertSee('class="alert-error" role="alert"', false)
            ->assertSee(__(Password::INVALID_TOKEN))
            ->assertSee('Request a new reset link');
    }

    public function test_login_page_labels_fields_and_supports_password_managers(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<label class="form-label" for="email">', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertDontSee('console.log', false)
            // Errors are no longer removed on a timer.
            ->assertDontSee('inlineErrors', false);
    }

    public function test_verify_page_has_no_dead_support_link_and_shows_a_loading_state(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('verification.notice'))
            ->assertOk()
            ->assertDontSee('href="#"', false)
            ->assertSee('id="resendSubmitBtn"', false);
    }
}
