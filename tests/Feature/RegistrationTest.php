<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'phone' => '123456789',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ], $overrides);
    }

    public function test_register_page_shows_first_last_name_and_fixed_09_prefix(): void
    {
        $html = $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('name="first_name"', $html);
        $this->assertStringContainsString('name="last_name"', $html);
        $this->assertStringNotContainsString('name="name"', $html);
        $this->assertStringContainsString('<span class="phone-prefix" aria-hidden="true">09</span>', $html);
    }

    public function test_registration_joins_names_and_prefixes_phone_with_09(): void
    {
        Event::fake([Registered::class]);

        $this->postJson('/register', $this->payload())->assertCreated();

        $user = User::where('email', 'juan@example.com')->firstOrFail();
        $this->assertSame('Juan Dela Cruz', $user->name);
        $this->assertSame('09123456789', $user->phone);
    }

    public function test_registration_trims_names_and_allows_empty_phone(): void
    {
        Event::fake([Registered::class]);

        $this->postJson('/register', $this->payload([
            'first_name' => '  Maria ',
            'last_name' => ' Santos  ',
            'phone' => '',
        ]))->assertCreated();

        $user = User::where('email', 'juan@example.com')->firstOrFail();
        $this->assertSame('Maria Santos', $user->name);
        $this->assertNull($user->phone);
    }

    public function test_registration_requires_both_names(): void
    {
        $this->postJson('/register', $this->payload(['first_name' => ' ', 'last_name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name']);
    }

    public function test_check_email_reports_taken_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->getJson('/register/check-email?email=taken@example.com')
            ->assertOk()
            ->assertJson([
                'available' => false,
                'message' => 'This email address is already associated with an account. Please log in or use a different email.',
            ]);
    }

    public function test_check_email_reports_available_email(): void
    {
        $this->getJson('/register/check-email?email=' . urlencode(' new@example.com '))
            ->assertOk()
            ->assertJson(['available' => true, 'message' => null]);
    }

    public function test_check_email_rejects_malformed_email(): void
    {
        $this->getJson('/register/check-email?email=not-an-email')
            ->assertOk()
            ->assertJson(['available' => false, 'message' => 'Please enter a valid email address.']);
    }

    public function test_register_page_wires_live_email_check(): void
    {
        $html = $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('register\/check-email', $html);
        $this->assertStringContainsString('action="/register" novalidate', $html);
    }

    public function test_registration_rejects_weak_passwords(): void
    {
        $cases = [
            'Short1' => 'Password must be at least 8 characters long.',
            'alllowercase1' => 'Password must include an uppercase letter, a lowercase letter, and a number.',
            'ALLUPPERCASE1' => 'Password must include an uppercase letter, a lowercase letter, and a number.',
            'NoNumbersHere' => 'Password must include an uppercase letter, a lowercase letter, and a number.',
        ];

        foreach ($cases as $password => $message) {
            $this->postJson('/register', $this->payload([
                'password' => $password,
                'password_confirmation' => $password,
            ]))
                ->assertStatus(422)
                ->assertJsonPath('errors.password.0', $message);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_mismatch_is_reported_on_confirmation_field(): void
    {
        $this->postJson('/register', $this->payload(['password_confirmation' => 'Different123']))
            ->assertStatus(422)
            ->assertJsonPath('errors.password_confirmation.0', 'Password confirmation does not match.');
    }

    public function test_register_page_shows_password_requirements(): void
    {
        $html = $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('id="passwordRules"', $html);
        foreach (['length', 'upper', 'lower', 'number'] as $rule) {
            $this->assertStringContainsString('data-rule="' . $rule . '"', $html);
        }
    }

    public function test_registration_rejects_phone_that_is_not_exactly_nine_digits(): void
    {
        foreach (['12345678', '1234567890', '12345678a'] as $phone) {
            $this->postJson('/register', $this->payload(['phone' => $phone]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['phone']);
        }
        $this->assertDatabaseCount('users', 0);
    }
}
