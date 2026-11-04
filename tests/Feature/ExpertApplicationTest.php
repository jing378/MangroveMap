<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AdminAlert;
use App\Notifications\ExpertApplicationReviewed;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ExpertApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana@example.com',
            'phone' => '',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms' => '1',
            'apply_as_expert' => '1',
            'organization' => 'DENR CENRO Maasin',
            'expert_qualifications' => 'Forester with eight years of mangrove rehabilitation work.',
        ], $overrides);
    }

    private function applicant(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'end_user',
            'organization' => 'SLSU',
            'expert_application_status' => User::EXPERT_APPLICATION_PENDING,
            'expert_qualifications' => 'Marine biology lecturer studying mangrove genera.',
            'expert_applied_at' => now(),
        ], $overrides));
    }

    public function test_registering_with_expert_application_creates_pending_resident_and_alerts_admins(): void
    {
        Event::fake([Registered::class]);
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->postJson('/register', $this->payload())->assertCreated();

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('end_user', $user->role);
        $this->assertSame(User::EXPERT_APPLICATION_PENDING, $user->expert_application_status);
        $this->assertSame('DENR CENRO Maasin', $user->organization);
        $this->assertNotNull($user->expert_applied_at);
        Notification::assertSentTo($admin, AdminAlert::class, fn ($n) => $n->type === 'expert_application');
    }

    public function test_admin_sees_expert_application_alert_without_a_queue_worker(): void
    {
        // Like the real app: queued jobs wait in the database until a worker runs.
        config(['queue.default' => 'database']);
        Event::fake([Registered::class]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->postJson('/register', $this->payload())->assertCreated();

        $notification = $admin->notifications()->first();
        $this->assertNotNull($notification, 'The in-app alert must not wait for the queue.');
        $this->assertSame('expert_application', $notification->data['type']);

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('New expert account application')
            ->assertSee('Review application')
            ->assertSee(route('admin.users') . '#expert-applications', false);
    }

    public function test_registering_without_expert_application_ignores_application_fields(): void
    {
        Event::fake([Registered::class]);
        Notification::fake();
        User::factory()->create(['role' => 'admin']);

        $this->postJson('/register', $this->payload([
            'apply_as_expert' => null,
            'organization' => '',
            'expert_qualifications' => '',
        ]))->assertCreated();

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertNull($user->expert_application_status);
        $this->assertNull($user->organization);
        Notification::assertNothingSent();
    }

    public function test_expert_application_requires_organization_and_qualifications(): void
    {
        $this->postJson('/register', $this->payload([
            'organization' => ' ',
            'expert_qualifications' => 'too short',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['organization', 'expert_qualifications']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_sees_pending_applications_on_users_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->applicant(['name' => 'Pending Applicant']);
        User::factory()->create(['name' => 'Plain Resident', 'role' => 'end_user']);

        $this->actingAs($admin)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Pending Expert Applications')
            ->assertSee('Marine biology lecturer studying mangrove genera.')
            ->assertSee(route('admin.expert-applications.approve', User::where('name', 'Pending Applicant')->first()));
    }

    public function test_admin_can_approve_application(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = $this->applicant();

        $this->actingAs($admin)
            ->post(route('admin.expert-applications.approve', $applicant))
            ->assertRedirect(route('admin.users'));

        $applicant->refresh();
        $this->assertSame('expert', $applicant->role);
        $this->assertSame(User::EXPERT_APPLICATION_APPROVED, $applicant->expert_application_status);
        $this->assertSame($admin->id, $applicant->expert_reviewed_by);
        $this->assertNotNull($applicant->expert_reviewed_at);
        Notification::assertSentTo($applicant, ExpertApplicationReviewed::class, fn ($n) => $n->approved);
        $this->assertDatabaseHas('user_activities', [
            'user_id' => $admin->id,
            'activity' => 'Approved expert application: ' . $applicant->name,
        ]);
    }

    public function test_admin_can_reject_application_with_reason(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = $this->applicant();

        $this->actingAs($admin)
            ->post(route('admin.expert-applications.reject', $applicant), [
                'rejection_notes' => 'Please provide proof of affiliation.',
            ])
            ->assertRedirect(route('admin.users'));

        $applicant->refresh();
        $this->assertSame('end_user', $applicant->role);
        $this->assertSame(User::EXPERT_APPLICATION_REJECTED, $applicant->expert_application_status);
        $this->assertSame('Please provide proof of affiliation.', $applicant->expert_rejection_notes);
        Notification::assertSentTo(
            $applicant,
            ExpertApplicationReviewed::class,
            fn ($n) => ! $n->approved && $n->notes === 'Please provide proof of affiliation.',
        );
    }

    public function test_already_reviewed_application_cannot_be_reviewed_again(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = $this->applicant(['expert_application_status' => User::EXPERT_APPLICATION_REJECTED]);

        $this->actingAs($admin)
            ->post(route('admin.expert-applications.approve', $applicant))
            ->assertSessionHas('error');

        $this->assertSame('end_user', $applicant->fresh()->role);
        Notification::assertNothingSent();
    }

    public function test_non_admins_cannot_review_applications(): void
    {
        $applicant = $this->applicant();

        // The admin middleware sends other roles back to their own dashboard.
        foreach (['end_user', 'expert'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)
                ->post(route('admin.expert-applications.approve', $applicant))
                ->assertRedirect($user->homeRoute());
        }

        $this->assertSame(User::EXPERT_APPLICATION_PENDING, $applicant->fresh()->expert_application_status);
    }

    public function test_manually_promoting_applicant_settles_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = $this->applicant();

        $this->actingAs($admin)->put(route('admin.users.update', $applicant), [
            'name' => $applicant->name,
            'email' => $applicant->email,
            'role' => 'expert',
        ]);

        $this->assertSame(User::EXPERT_APPLICATION_APPROVED, $applicant->fresh()->expert_application_status);
    }
}
