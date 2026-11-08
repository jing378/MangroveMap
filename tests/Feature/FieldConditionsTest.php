<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FieldWeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FieldConditionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-11-08 09:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fakeOpenMeteo(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([
            'current' => [
                'temperature_2m' => 29.4, 'relative_humidity_2m' => 78, 'precipitation' => 0,
                'weather_code' => 2, 'wind_speed_10m' => 11.2, 'wind_gusts_10m' => 24.8,
            ],
            'daily' => [
                'time' => ['2026-11-08', '2026-11-09', '2026-11-10', '2026-11-11'],
                'weather_code' => [2, 80, 95, 3],
                'temperature_2m_max' => [31.2, 30.1, 28.4, 30.0],
                'temperature_2m_min' => [24.6, 24.9, 24.1, 24.3],
                'precipitation_probability_max' => [20, 70, 90, 35],
                'precipitation_sum' => [0.4, 6.2, 31.0, 1.1],
                'wind_speed_10m_max' => [14, 18, 35, 16],
                'wind_gusts_10m_max' => [28, 36, 72, 30],
            ],
        ])]);
    }

    public function test_dashboard_shows_the_card_without_calling_the_weather_service(): void
    {
        Http::fake();
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Field Conditions')
            ->assertSee(route('dashboard.field-conditions'));

        Http::assertNothingSent();
    }

    public function test_experts_see_the_card_too_but_admins_do_not(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $this->actingAs($expert)->get(route('expert.dashboard'))->assertOk()->assertSee('Field Conditions');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Field Conditions');
    }

    public function test_forecast_card_shows_only_todays_field_work_rating(): void
    {
        $this->fakeOpenMeteo();
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)
            ->get(route('dashboard.field-conditions'))
            ->assertOk()
            ->assertSee('29°C')
            ->assertSee('Partly cloudy')
            ->assertSee('Good for field work today')
            ->assertDontSee('Tomorrow')
            ->assertSee('Open-Meteo.com');
    }

    public function test_the_forecast_is_cached_so_the_api_is_called_once(): void
    {
        $this->fakeOpenMeteo();
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)->get(route('dashboard.field-conditions'))->assertOk();
        $this->actingAs($resident)->get(route('dashboard.field-conditions'))->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'latitude=10.358')
            && str_contains($request->url(), 'timezone=Asia%2FManila'));
    }

    public function test_the_forecast_follows_the_users_location_rounded_and_cached_per_spot(): void
    {
        $this->fakeOpenMeteo();
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)
            ->get(route('dashboard.field-conditions', ['lat' => 10.13456, 'lng' => 125.01234]))
            ->assertOk()
            ->assertSee('Near you')
            ->assertSee('Your location') // no boundaries on SQLite, so the generic label
            ->assertDontSee('Maasin City');

        // A few metres away shares the cached forecast; a different town does not.
        $this->actingAs($resident)->get(route('dashboard.field-conditions', ['lat' => 10.13499, 'lng' => 125.01001]))->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'latitude=10.13')
            && str_contains($request->url(), 'longitude=125.01'));

        $this->actingAs($resident)->get(route('dashboard.field-conditions', ['lat' => 10.35, 'lng' => 124.97]))->assertOk();
        Http::assertSentCount(2);
    }

    public function test_without_a_location_it_shows_the_default_place(): void
    {
        $this->fakeOpenMeteo();
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)
            ->get(route('dashboard.field-conditions'))
            ->assertOk()
            ->assertSee('Maasin City, Southern Leyte')
            ->assertSee('allow location access');
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        Http::fake();
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)
            ->getJson(route('dashboard.field-conditions', ['lat' => 120, 'lng' => 125]))
            ->assertUnprocessable();
        $this->actingAs($resident)
            ->getJson(route('dashboard.field-conditions', ['lat' => 10.1]))
            ->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_when_the_weather_service_is_down_the_card_says_so_and_retries_later(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response('down', 503)]);
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)
            ->get(route('dashboard.field-conditions'))
            ->assertOk()
            ->assertSee('The weather forecast is unavailable right now');

        // Not asked again right away...
        $this->actingAs($resident)->get(route('dashboard.field-conditions'));
        Http::assertSentCount(1);

        // ...but after the retry window.
        Carbon::setTestNow(now()->addMinutes(11));
        $this->actingAs($resident)->get(route('dashboard.field-conditions'));
        Http::assertSentCount(2);
    }

    public function test_field_ratings_follow_the_thresholds(): void
    {
        $this->assertSame('good', FieldWeatherService::fieldRating(['code' => 1, 'gust_max' => 20, 'rain_mm' => 1, 'rain_chance' => 20])['rating']);
        $this->assertSame('caution', FieldWeatherService::fieldRating(['code' => 80, 'gust_max' => 20, 'rain_mm' => 6, 'rain_chance' => 50])['rating']);
        $this->assertSame('caution', FieldWeatherService::fieldRating(['code' => 2, 'gust_max' => 45, 'rain_mm' => 0, 'rain_chance' => 10])['rating']);
        $this->assertSame('avoid', FieldWeatherService::fieldRating(['code' => 95, 'gust_max' => 10, 'rain_mm' => 0, 'rain_chance' => 10])['rating']);
        $this->assertSame('avoid', FieldWeatherService::fieldRating(['code' => 63, 'gust_max' => 20, 'rain_mm' => 25, 'rain_chance' => 90])['rating']);
        $this->assertSame('avoid', FieldWeatherService::fieldRating(['code' => 3, 'gust_max' => 65, 'rain_mm' => 0, 'rain_chance' => 10])['rating']);
    }

    public function test_guests_cannot_load_the_card(): void
    {
        $this->get(route('dashboard.field-conditions'))->assertRedirect(route('login'));
    }
}
