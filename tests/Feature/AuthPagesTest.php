<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
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

    private function verificationLink(User $user, ?string $email = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($email ?? $user->email),
        ]);
    }

    public function test_verification_link_verifies_then_sends_the_user_to_login_not_the_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        // Still signed in from registration, same browser: verified, signed out, sent to login.
        $this->actingAs($user)->get($this->verificationLink($user))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Email verified successfully! Please log in to continue.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
    }

    public function test_verification_link_works_without_being_logged_in_and_rejects_a_wrong_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationLink($user))->assertRedirect(route('login'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $other = User::factory()->unverified()->create();
        $this->get($this->verificationLink($other, 'someone@else.test'))->assertForbidden();
        $this->get($this->verificationLink($other).'x')->assertForbidden(); // broken signature
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_verify_notice_sends_an_already_verified_user_to_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('verification.notice'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
