<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use App\Notifications\AdminAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_shows_send_feedback_for_residents_and_admins(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $this->actingAs($resident)->get(route('guide'))
            ->assertOk()
            ->assertSee('Send Feedback')
            ->assertSee('id="feedbackDialog"', false);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Send Feedback')
            ->assertSee(route('admin.feedback.index'));
    }

    public function test_user_can_send_feedback_with_screenshot_and_admins_are_notified(): void
    {
        Storage::fake(Feedback::SCREENSHOT_DISK);
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)
            ->postJson(route('feedback.store'), [
                'type' => 'bug',
                'message' => 'The draw button does nothing on my phone.',
                'page_url' => url('/delineate?x=1'),
                'screenshot' => UploadedFile::fake()->image('shot.png'),
            ])
            ->assertCreated();

        $feedback = Feedback::sole();
        $this->assertSame($resident->id, $feedback->user_id);
        $this->assertSame('bug', $feedback->type);
        $this->assertSame('new', $feedback->status);
        $this->assertSame('/delineate?x=1', $feedback->page_url);
        Storage::disk(Feedback::SCREENSHOT_DISK)->assertExists($feedback->screenshot_path);
        Notification::assertSentTo($admin, AdminAlert::class);
    }

    public function test_feedback_from_another_site_url_drops_the_page(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)
            ->postJson(route('feedback.store'), [
                'type' => 'suggestion',
                'message' => 'Please add a dark map style.',
                'page_url' => 'https://evil.example/phish',
            ])
            ->assertCreated();

        $this->assertNull(Feedback::sole()->page_url);
    }

    public function test_feedback_is_validated(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)
            ->postJson(route('feedback.store'), ['type' => 'nope', 'message' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'message']);

        $this->assertDatabaseCount('feedback', 0);
    }

    public function test_only_admins_can_view_and_manage_feedback(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $feedback = Feedback::create([
            'user_id' => $resident->id,
            'type' => 'question',
            'message' => 'How do I revise a rejected area?',
            'status' => 'new',
        ]);

        // The admin middleware sends non-admins back to their own home page.
        $this->actingAs($resident)->get(route('admin.feedback.index'))->assertRedirect();
        $this->actingAs($resident)->put(route('admin.feedback.update', $feedback), ['status' => 'resolved'])->assertRedirect();
        $this->assertSame('new', $feedback->fresh()->status);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.feedback.index'))
            ->assertOk()
            ->assertSee('How do I revise a rejected area?');

        $this->actingAs($admin)->put(route('admin.feedback.update', $feedback), ['status' => 'resolved'])->assertRedirect();
        $this->assertSame('resolved', $feedback->fresh()->status);

        $this->actingAs($admin)->delete(route('admin.feedback.destroy', $feedback))->assertRedirect();
        $this->assertDatabaseCount('feedback', 0);
    }
}
