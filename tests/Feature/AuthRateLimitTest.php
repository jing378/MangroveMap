<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, string $password, string $ip = '203.0.113.5')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from(route('login'))
            ->post('/login', ['email' => $email, 'password' => $password]);
    }

    public function test_too_many_wrong_passwords_lock_that_email_out_for_a_minute(): void
    {
        User::factory()->create(['email' => 'resident@example.com', 'password' => bcrypt('Secret123')]);

        for ($i = 0; $i < AuthenticatedSessionController::MAX_ATTEMPTS; $i++) {
            $this->login('resident@example.com', 'wrong-'.$i)
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['email' => 'Invalid credentials']);
        }

        // Even the right password is refused during the lockout, with a clear message.
        $this->login('resident@example.com', 'Secret123')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest();

        // A minute later it works again.
        $this->travel(61)->seconds();
        $this->login('resident@example.com', 'Secret123')->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_lockout_is_per_email_and_ip(): void
    {
        User::factory()->create(['email' => 'resident@example.com', 'password' => bcrypt('Secret123')]);

        for ($i = 0; $i < AuthenticatedSessionController::MAX_ATTEMPTS; $i++) {
            $this->login('resident@example.com', 'wrong', '203.0.113.5');
        }

        // The real owner on another network can still log in.
        $this->login('resident@example.com', 'Secret123', '198.51.100.7');
        $this->assertAuthenticated();
    }

    public function test_successful_login_resets_the_count(): void
    {
        User::factory()->create(['email' => 'resident@example.com', 'password' => bcrypt('Secret123')]);

        for ($i = 0; $i < AuthenticatedSessionController::MAX_ATTEMPTS - 1; $i++) {
            $this->login('resident@example.com', 'wrong');
        }
        $this->login('resident@example.com', 'Secret123');
        $this->assertAuthenticated();
        $this->post(route('logout'));

        // A fresh set of attempts is allowed after a successful login.
        for ($i = 0; $i < AuthenticatedSessionController::MAX_ATTEMPTS - 1; $i++) {
            $this->login('resident@example.com', 'wrong')->assertSessionHasErrors(['email' => 'Invalid credentials']);
        }
    }

    public function test_one_ip_cannot_try_unlimited_emails(): void
    {
        // 30 login attempts per minute per IP, across all emails.
        for ($i = 0; $i < 30; $i++) {
            $this->login("someone{$i}@example.com", 'guess');
        }

        $this->login('another@example.com', 'guess')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many attempts', session('errors')->first('email'));
    }

    public function test_forgot_password_is_limited_per_ip_with_a_friendly_message(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
                ->from(route('password.request'))
                ->post(route('password.email'), ['email' => "nobody{$i}@example.com"]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'nobody-else@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many attempts', session('errors')->first('email'));
    }

    public function test_register_is_limited_per_ip_and_answers_json_with_429(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])->post('/register', []);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
            ->postJson('/register', [])
            ->assertStatus(429)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'Too many attempts'));
    }
}
