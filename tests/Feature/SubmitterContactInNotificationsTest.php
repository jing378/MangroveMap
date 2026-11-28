<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use App\Notifications\DelineationSubmittedForReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubmitterContactInNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expert_sees_the_submitters_email_and_phone(): void
    {
        $resident = User::factory()->create([
            'role' => 'end_user', 'email' => 'resident@example.com', 'phone' => '0917 123 4567',
        ]);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = Delineation::create(['user_id' => $resident->id, 'name' => 'Mangrove A', 'features' => []]);

        $expert->notify(new DelineationSubmittedForReview($delineation));

        $this->actingAs($expert)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('mailto:resident@example.com', false)
            ->assertSee('tel:09171234567', false)
            ->assertSee('0917 123 4567');
    }

    public function test_older_notifications_without_a_submitter_id_find_them_through_the_delineation(): void
    {
        $resident = User::factory()->create(['role' => 'end_user', 'email' => 'old@example.com']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = Delineation::create(['user_id' => $resident->id, 'name' => 'Mangrove B', 'features' => []]);

        $expert->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => DelineationSubmittedForReview::class,
            'data' => [
                'title' => 'Delineation submitted for review',
                'type' => 'delineation_submitted',
                'delineation_id' => $delineation->id,
                'submitted_by' => $resident->name,
            ],
        ]);

        $this->actingAs($expert)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('mailto:old@example.com', false);
    }
}
