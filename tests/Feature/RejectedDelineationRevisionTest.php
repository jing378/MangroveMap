<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use App\Notifications\DelineationSubmittedForReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RejectedDelineationRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_revise_rejected_delineation_and_resubmit_it(): void
    {
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $originalFeatures = [[
            'type' => 'area',
            'coords' => [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01]],
            'genera' => ['Rhizophora'],
        ], [
            'type' => 'point',
            'coords' => [10.02, 124.02],
            'genera' => ['Avicennia'],
        ]];
        $delineation = Delineation::create([
            'user_id' => $resident->id,
            'name' => 'Original boundary',
            'notes' => 'Original notes',
            'features' => $originalFeatures,
            'is_rejected' => true,
            'rejection_notes' => 'Please correct the northern edge.',
        ]);
        $revisedFeatures = [[
            'type' => 'area',
            'coords' => [[10.0, 124.0], [10.0, 124.02], [10.01, 124.01]],
            'genera' => ['Nypa'],
        ], [
            'type' => 'point',
            'coords' => [10.03, 124.03],
            'genera' => ['Avicennia'],
        ]];

        $response = $this->actingAs($resident)->putJson(
            route('delineations.revise', $delineation),
            [
                'name' => 'Revised boundary',
                'notes' => 'Corrected the northern edge.',
                'features' => $revisedFeatures,
            ],
        );

        $response->assertOk()
            ->assertJsonPath('delineation.name', 'Revised boundary')
            ->assertJsonPath('delineation.is_rejected', false)
            ->assertJsonPath('delineation.is_approved', false)
            ->assertJsonCount(2, 'delineation.features');

        $this->assertDatabaseHas('delineation_revisions', [
            'delineation_id' => $delineation->id,
            'version' => 1,
        ]);
        $this->assertEquals($originalFeatures, $delineation->revisions()->firstOrFail()->snapshot['features']);
        Notification::assertSentTo(
            $expert,
            DelineationSubmittedForReview::class,
            fn(DelineationSubmittedForReview $notification) => $notification->isRevision,
        );
    }

    public function test_expert_delineate_page_shows_save_button(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $this->actingAs($expert);

        $route = new Route(['GET'], '/expert/delineate', ['uses' => fn() => null]);
        $route->name('expert.delineate');
        $request = Request::create('/expert/delineate', 'GET');
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

        $this->assertMatchesRegularExpression(
            '/id="saveBtn" type="button" class="btn btn-g delineation-save-btn[^"]*"/',
            $html,
        );
    }

    public function test_resident_delineate_page_keeps_changes_over_time_available(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $this->actingAs($resident);

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

        $this->assertStringContainsString('id="mapTimelineControl"', $html);
        $this->assertStringContainsString('Changes over time', $html);
        $this->assertStringNotContainsString("body[data-page='delineate'] #mapTimelineControl", $html);
    }

    public function test_only_owner_can_revise_and_pending_delineations_remain_locked(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);
        $otherResident = User::factory()->create(['role' => 'end_user']);
        $rejected = Delineation::create([
            'user_id' => $owner->id,
            'name' => 'Rejected boundary',
            'features' => [['type' => 'point', 'coords' => [10.0, 124.0]]],
            'is_rejected' => true,
        ]);
        $pending = Delineation::create([
            'user_id' => $owner->id,
            'name' => 'Pending boundary',
            'features' => [['type' => 'point', 'coords' => [10.0, 124.0]]],
        ]);
        $approved = Delineation::create([
            'user_id' => $owner->id,
            'name' => 'Approved boundary',
            'features' => [['type' => 'point', 'coords' => [10.0, 124.0]]],
            'is_approved' => true,
        ]);
        $payload = [
            'name' => 'Revised boundary',
            'notes' => 'Updated notes.',
            'features' => [['type' => 'point', 'coords' => [10.01, 124.01]]],
        ];

        $this->actingAs($otherResident)
            ->putJson(route('delineations.revise', $rejected), $payload)
            ->assertForbidden();

        $this->actingAs($owner)
            ->putJson(route('delineations.revise', $pending), $payload)
            ->assertStatus(409);

        $this->actingAs($owner)
            ->putJson(route('delineations.revise', $approved), $payload)
            ->assertStatus(409);
    }
}