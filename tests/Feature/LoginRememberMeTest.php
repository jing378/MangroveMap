<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoginRememberMeTest extends TestCase
{
    use RefreshDatabase;

    private function rememberCookieName(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    public function test_checking_remember_me_sets_a_remember_cookie(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Secret123')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secret123',
            'remember' => 'on',
        ]);

        $response->assertCookie($this->rememberCookieName());
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_remember_me_logs_the_user_back_in_after_the_session_expired(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Secret123'), 'role' => 'end_user']);

        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secret123',
            'remember' => 'on',
        ]);
        $remember = $login->getCookie($this->rememberCookieName(), false);
        $this->assertNotNull($remember);
        // Lasts far longer than the 120-minute session.
        $this->assertGreaterThan(now()->addDays(30)->getTimestamp(), $remember->getExpiresTime());

        // The session runs out (120 minutes without use, or the app was closed for days). Not a logout: logging
        // out on purpose also invalidates the remember cookie.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->assertGuest();

        // …the browser still sends the remember cookie, so the next visit is logged in again.
        $this->withUnencryptedCookie($this->rememberCookieName(), $remember->getValue())
            ->get(route('dashboard'))
            ->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_without_remember_me_an_expired_session_means_logging_in_again(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Secret123'), 'role' => 'end_user']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123']);
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logging_out_on_purpose_also_ends_remember_me(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Secret123'), 'role' => 'end_user']);

        $login = $this->post('/login', ['email' => $user->email, 'password' => 'Secret123', 'remember' => 'on']);
        $remember = $login->getCookie($this->rememberCookieName(), false);

        $this->post(route('logout'));
        $this->app['auth']->forgetGuards();

        $this->withUnencryptedCookie($this->rememberCookieName(), $remember->getValue())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_without_remember_me_does_not_set_a_remember_cookie(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Secret123')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secret123',
        ]);

        $response->assertCookieMissing($this->rememberCookieName());
        $this->assertAuthenticatedAs($user);
    }
}
