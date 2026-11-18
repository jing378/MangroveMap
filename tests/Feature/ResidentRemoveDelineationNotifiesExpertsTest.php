<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use App\Notifications\DelineationWithdrawn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResidentRemoveDelineationNotifiesExpertsTest extends TestCase
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

    public function test_removing_a_pending_delineation_notifies_every_expert(): void
    {
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user', 'name' => 'Ana Resident']);
        $experts = User::factory()->count(2)->create(['role' => 'expert']);
        $pending = $this->makeDelineation($resident);

        $this->actingAs($resident)
            ->deleteJson(route('delineations.destroy', $pending))
            ->assertOk();

        $this->assertModelMissing($pending);
        Notification::assertSentTo($experts, DelineationWithdrawn::class, function (DelineationWithdrawn $notification, array $channels, User $expert) {
            $data = $notification->toArray($expert);

            return $notification->status === 'pending'
                && $data['type'] === 'delineation_withdrawn'
                && $data['message'] === 'Ana Resident removed their pending delineation "Mangrove patch". It no longer needs review.'
                && $data['url'] === route('expert.delineate');
        });
        Notification::assertNotSentTo($resident, DelineationWithdrawn::class);
    }

    public function test_removing_a_rejected_delineation_notifies_the_expert_who_rejected_it(): void
    {
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $reviewer = User::factory()->create(['role' => 'expert']);
        $otherExpert = User::factory()->create(['role' => 'expert']);
        $rejected = $this->makeDelineation($resident, [
            'is_rejected' => true,
            'rejected_at' => now(),
            'rejected_by' => $reviewer->id,
        ]);

        $this->actingAs($resident)
            ->deleteJson(route('delineations.destroy', $rejected))
            ->assertOk();

        Notification::assertSentTo($reviewer, DelineationWithdrawn::class, fn (DelineationWithdrawn $n) => $n->status === 'rejected');
        Notification::assertNotSentTo($otherExpert, DelineationWithdrawn::class);
    }

    public function test_approved_delineations_cannot_be_removed_so_nobody_is_notified(): void
    {
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        User::factory()->create(['role' => 'expert']);
        $approved = $this->makeDelineation($resident, ['is_approved' => true, 'approved_at' => now()]);

        $this->actingAs($resident)
            ->deleteJson(route('delineations.destroy', $approved))
            ->assertUnprocessable();

        $this->assertModelExists($approved);
        Notification::assertNothingSent();
    }
}
