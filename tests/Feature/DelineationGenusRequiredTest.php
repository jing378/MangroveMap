<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DelineationGenusRequiredTest extends TestCase
{
    use RefreshDatabase;

    private const AREA = [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01], [10.0, 124.0]];

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Riverside stand',
            'notes' => 'Dense mature stand along the river.',
            'features' => [['type' => 'area', 'coords' => self::AREA]],
            'genera' => ['Rhizophora'],
        ], $overrides);
    }

    public static function saveRoutes(): array
    {
        return [
            'resident' => ['end_user', 'delineations.store'],
            'expert' => ['expert', 'expert.delineations.store'],
        ];
    }

    #[DataProvider('saveRoutes')]
    public function test_saving_without_genus_is_rejected(string $role, string $route): void
    {
        $user = User::factory()->create(['role' => $role]);

        foreach ([
            [$this->payload(['genera' => []]), 'genera'],
            [$this->payload(['genera' => null]), 'genera'],
            // Blank strings are trimmed to null and fail the per-item string rule.
            [$this->payload(['genera' => ['  ']]), 'genera.0'],
            // A feature-level empty list overrides the top-level genera.
            [$this->payload(['features' => [['type' => 'area', 'coords' => self::AREA, 'genera' => []]]]), 'genera'],
        ] as [$payload, $errorKey]) {
            $this->actingAs($user)
                ->postJson(route($route), $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors([$errorKey]);
        }

        $this->assertSame(0, Delineation::count());
    }

    #[DataProvider('saveRoutes')]
    public function test_saving_with_genus_succeeds(string $role, string $route): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->postJson(route($route), $this->payload())
            ->assertOk();

        $delineation = Delineation::firstOrFail();
        $this->assertSame(['Rhizophora'], $delineation->features[0]['genera']);
    }

    public function test_delineate_page_save_button_checks_genus_and_notes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'end_user']));

        $route = new Route(['GET'], '/delineate', ['uses' => fn() => null]);
        $route->name('delineate');
        $request = Request::create('/delineate', 'GET');
        $request->setRouteResolver(fn() => $route);
        $this->app->instance('request', $request);

        $html = view('users.delineate', [
            'delineations' => [],
            'approvedDelineationsForMap' => [],
            'residentDelineationsForMap' => [],
            'focusDelineationId' => null,
            'focusDelineationRecord' => null,
            'page' => 'delineate',
        ])->render();

        $this->assertStringContainsString('id="saveBtn"', $html);

        $this->assertStringContainsString('const generaSelected = Array.isArray(currentSelectedGenera)', $html);
        $this->assertStringContainsString('!generaSelected || !notesFilled', $html);
    }
}
