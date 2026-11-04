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
