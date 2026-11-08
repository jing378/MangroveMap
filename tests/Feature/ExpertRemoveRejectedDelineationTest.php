<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use App\Notifications\DelineationRejected;
use App\Notifications\DelineationRemoved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ExpertRemoveRejectedDelineationTest extends TestCase
{
    use RefreshDatabase;

    private function makeDelineation(User $owner, array $attributes = []): Delineation
    {
        return Delineation::create(array_merge([
            'user_id' => $owner->id,
            'name' => 'Mangrove patch',
            'features' => [['type' => 'point', 'coords' => [10.0, 124.0]]],
        ], $attributes));
    }

    public function test_expert_can_remove_a_rejected_delineation_and_the_resident_is_notified(): void
    {
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $rejected = $this->makeDelineation($resident, [
            'is_rejected' => true,
            'rejected_at' => now()->subDays(Delineation::REJECTED_REMOVAL_GRACE_DAYS)->subMinute(),
            'rejection_notes' => 'Boundary is off the coastline.',
        ]);

        $this->actingAs($expert)
            ->deleteJson(route('expert.delineations.destroy', $rejected))
            ->assertOk();

        $this->assertDatabaseMissing('delineations', ['id' => $rejected->id]);
        Notification::assertSentTo(
            $resident,
            DelineationRemoved::class,
            fn(DelineationRemoved $notification) => $notification->delineationName === 'Mangrove patch',
        );
    }

    public function test_expert_cannot_remove_a_rejected_delineation_during_the_residents_revision_week(): void
    {
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $rejected = $this->makeDelineation($resident, [
            'is_rejected' => true,
            'rejected_at' => now()->subDays(Delineation::REJECTED_REMOVAL_GRACE_DAYS - 1),
        ]);

        $this->actingAs($expert)
            ->deleteJson(route('expert.delineations.destroy', $rejected))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'The resident still has time to revise this delineation. It can be removed after '
                . $rejected->rejectedRemovableAt()->timezone(config('app.timezone'))->format('M j, Y g:i A') . '.']);

        $this->assertDatabaseHas('delineations', ['id' => $rejected->id]);
        Notification::assertNothingSent();
    }

    public function test_rejecting_tells_the_resident_about_the_one_week_revision_period(): void
    {
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $pending = $this->makeDelineation($resident);

        $this->actingAs($expert)
            ->postJson(route('expert.delineations.reject', $pending), ['rejection_notes' => 'Boundary is off the coastline.'])
            ->assertOk()
            ->assertJsonPath('removable_after', $pending->fresh()->rejectedRemovableAt()->toIso8601String());

        Notification::assertSentTo($resident, DelineationRejected::class, function (DelineationRejected $notification) use ($resident) {
            $message = $notification->toArray($resident)['message'];

            return str_contains($message, 'If it is not revised within 7 days')
                && str_contains($message, 'experts may remove it.');
        });
    }

    public function test_expert_can_remove_approved_and_pending_delineations_and_the_owner_is_notified(): void
    {
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $pending = $this->makeDelineation($resident, ['name' => 'Pending patch']);
        $approved = $this->makeDelineation($resident, ['name' => 'Approved patch', 'is_approved' => true]);

        $this->actingAs($expert)
            ->deleteJson(route('expert.delineations.destroy', $pending))
            ->assertOk();
        $this->actingAs($expert)
            ->deleteJson(route('expert.delineations.destroy', $approved))
            ->assertOk()
            ->assertJsonPath('message', 'Approved delineation removed. The owner has been notified.');

        $this->assertDatabaseMissing('delineations', ['id' => $pending->id]);
        $this->assertDatabaseMissing('delineations', ['id' => $approved->id]);
        Notification::assertSentTo(
            $resident,
            DelineationRemoved::class,
            fn(DelineationRemoved $n) => $n->delineationName === 'Approved patch' && $n->status === 'approved',
        );
        Notification::assertSentTo(
            $resident,
            DelineationRemoved::class,
            fn(DelineationRemoved $n) => $n->delineationName === 'Pending patch' && $n->status === 'pending',
        );
    }

    public function test_expert_can_remove_their_own_approved_delineation_without_a_notification(): void
    {
        Notification::fake();
        $expert = User::factory()->create(['role' => 'expert']);
        $own = $this->makeDelineation($expert, ['is_approved' => true]);

        $this->actingAs($expert)
            ->deleteJson(route('expert.delineations.destroy', $own))
            ->assertOk();

        $this->assertDatabaseMissing('delineations', ['id' => $own->id]);
        Notification::assertNothingSent();
    }

    public function test_residents_cannot_use_the_expert_remove_route(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);
        $otherResident = User::factory()->create(['role' => 'end_user']);
        $rejected = $this->makeDelineation($owner, ['is_rejected' => true]);

        $this->actingAs($otherResident)
            ->deleteJson(route('expert.delineations.destroy', $rejected))
            ->assertRedirect(); // the expert middleware sends other roles back to their own home page

        $this->assertDatabaseHas('delineations', ['id' => $rejected->id]);
    }
}
