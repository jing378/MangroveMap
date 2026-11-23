<?php

namespace Tests\Feature;

use App\Models\AdminReminder;
use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Notifications\ReminderFromAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminReminderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function pendingDelineation(User $owner, array $attributes = []): Delineation
    {
        return Delineation::forceCreate(array_merge([
            'user_id' => $owner->id,
            'name' => 'Sogod Bay Stand',
            'features' => [['type' => 'area', 'coords' => [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01], [10.0, 124.0]], 'genera' => ['Rhizophora']]],
            'created_at' => now()->subDays(5),
        ], $attributes));
    }

    private function waitingLayer(User $owner, string $name, int $daysAgo = 10): DroneImageryLayer
    {
        return DroneImageryLayer::create([
            'user_id' => $owner->id, 'name' => $name, 'status' => 'awaiting_review', 'source_type' => 'single',
            'segmentation_status' => 'ready', 'segmented_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_reminding_the_experts_about_pending_reviews_reaches_every_expert_once_a_day(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $experts = User::factory()->count(2)->create(['role' => 'expert']);
        $resident = User::factory()->create(['role' => 'end_user']);
        $this->pendingDelineation($resident);

        $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->post(route('admin.reminders.store'), ['kind' => 'pending_reviews', 'note' => 'Before Friday, please.'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Reminder sent to 2 people: 1 pending delineation (experts).');

        Notification::assertSentTo($experts, ReminderFromAdmin::class, function (ReminderFromAdmin $n, array $channels, User $expert) {
            $data = $n->toArray($expert);

            return $data['url'] === route('expert.delineate', [], false)
                && str_contains($data['message'], '1 delineation is waiting for expert review')
                && str_contains($data['message'], 'Before Friday, please.');
        });
        Notification::assertNotSentTo($resident, ReminderFromAdmin::class);
        $this->assertDatabaseHas('admin_reminders', ['kind' => 'pending_reviews', 'recipients' => 2, 'sent_by' => $admin->id]);
        $this->assertDatabaseHas('user_activities', ['module' => 'Reminders', 'user_id' => $admin->id]);

        // The same reminder waits a day.
        $this->actingAs($admin)->post(route('admin.reminders.store'), ['kind' => 'pending_reviews'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'already sent'));
        $this->assertSame(1, AdminReminder::count());

        $this->travel(25)->hours();
        $this->actingAs($admin)->post(route('admin.reminders.store'), ['kind' => 'pending_reviews'])->assertSessionHas('success');
    }

    public function test_one_pending_delineation_links_experts_to_it(): void
    {
        Notification::fake();
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->pendingDelineation(User::factory()->create(['role' => 'end_user']));

        $this->actingAs($this->admin())
            ->post(route('admin.reminders.store'), ['kind' => 'delineation', 'target_id' => $delineation->id])
            ->assertSessionHas('success');

        Notification::assertSentTo($expert, ReminderFromAdmin::class, fn (ReminderFromAdmin $n) => $n->url === route('expert.delineate', ['delineation' => $delineation->id], false)
            && str_contains($n->title, 'Sogod Bay Stand'));

        // An approved one is no longer waiting: nothing is sent.
        $approved = $this->pendingDelineation(User::factory()->create(), ['is_approved' => true]);
        $this->actingAs($this->admin())
            ->post(route('admin.reminders.store'), ['kind' => 'delineation', 'target_id' => $approved->id])
            ->assertSessionHas('error', 'That delineation is not waiting for review any more.');
    }

    public function test_stale_drafts_remind_each_owner_once_naming_their_surveys(): void
    {
        Notification::fake();
        $alice = User::factory()->create(['role' => 'end_user']);
        $bob = User::factory()->create(['role' => 'end_user']);
        $carol = User::factory()->create(['role' => 'end_user']);
        $this->waitingLayer($alice, 'Maasin north');
        $this->waitingLayer($alice, 'Maasin south');
        $this->waitingLayer($bob, 'Bontoc pier');
        $this->waitingLayer($carol, 'Fresh upload', 1); // not stale yet

        $this->actingAs($this->admin())
            ->post(route('admin.reminders.store'), ['kind' => 'stale_drafts'])
            ->assertSessionHas('success', 'Reminder sent to 2 people: 3 AI drafts waiting for 2 owners.');

        Notification::assertSentToTimes($alice, ReminderFromAdmin::class, 1);
        Notification::assertSentTo($alice, ReminderFromAdmin::class, fn (ReminderFromAdmin $n) => str_contains($n->message, '"Maasin north", "Maasin south"')
            && $n->url === route('drone-imagery.page', [], false));
        Notification::assertSentTo($bob, ReminderFromAdmin::class, fn (ReminderFromAdmin $n) => $n->url === route('drone-imagery.page', ['layer' => DroneImageryLayer::where('name', 'Bontoc pier')->value('id')], false));
        Notification::assertNotSentTo($carol, ReminderFromAdmin::class);
    }

    public function test_one_drone_survey_reminds_its_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'end_user']);
        $layer = $this->waitingLayer($owner, 'Libagon cove', 2);

        $this->actingAs($this->admin())
            ->post(route('admin.reminders.store'), ['kind' => 'drone_layer', 'target_id' => $layer->id])
            ->assertSessionHas('success', 'Reminder sent to 1 person: "Libagon cove" waiting for '.$owner->name.'.');

        Notification::assertSentTo($owner, ReminderFromAdmin::class);
    }

    public function test_dashboard_and_map_layers_offer_the_reminder_buttons(): void
    {
        Notification::fake();
        $admin = $this->admin();
        User::factory()->create(['role' => 'expert']);
        $resident = User::factory()->create(['role' => 'end_user']);
        $pending = $this->pendingDelineation($resident);
        $approved = $this->pendingDelineation($resident, ['name' => 'Approved stand', 'is_approved' => true]);
        $layer = $this->waitingLayer($resident, 'Maasin north');

        // Dashboard: "Send reminder" on the overdue-review and stale-draft items.
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-reminder-kind="pending_reviews"', false)
            ->assertSee('data-reminder-kind="stale_drafts"', false)
            ->assertSee('id="reminderDialog"', false);

        // Map Layers: a bell on the pending delineation only, and on the survey waiting for its owner.
        $html = $this->actingAs($admin)->get(route('admin.map-layers.index'))->getContent();
        $this->assertStringContainsString('data-reminder-kind="delineation" data-reminder-target="'.$pending->id.'"', $html);
        $this->assertStringNotContainsString('data-reminder-target="'.$approved->id.'"', $html);
        $this->actingAs($admin)->get(route('admin.map-layers.index', ['tab' => 'imagery']))
            ->assertSee('data-reminder-kind="drone_layer" data-reminder-target="'.$layer->id.'"', false);

        // After sending, the button says so instead.
        $this->actingAs($admin)->post(route('admin.reminders.store'), ['kind' => 'pending_reviews']);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertDontSee('data-reminder-kind="pending_reviews"', false)
            ->assertSee('Reminded');
    }

    public function test_only_admins_send_reminders_and_the_kind_is_checked(): void
    {
        Notification::fake();
        foreach (['expert', 'end_user'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->post(route('admin.reminders.store'), ['kind' => 'pending_reviews'])
                ->assertRedirect($user->homeRoute());
        }

        $this->actingAs($this->admin())->post(route('admin.reminders.store'), ['kind' => 'everyone'])
            ->assertSessionHasErrors('kind');
        $this->assertSame(0, AdminReminder::count());
        Notification::assertNothingSent();
    }
}
