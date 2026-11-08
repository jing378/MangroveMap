<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AdminAlert;
use App\Notifications\ExpertApplicationReviewed;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpertApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep uploaded certificates out of the real storage folder.
        Storage::fake('local');
    }

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
            'expert_certificate' => UploadedFile::fake()->create('forester-license.pdf', 120, 'application/pdf'),
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
            ->assertSee('Applicant:')
            ->assertSee('DENR CENRO Maasin')
            ->assertSee('Review')
            ->assertDontSee('Review application')
            ->assertSee('href="' . route('admin.users', [], false) . '#expert-applications"', false);
    }

    /** Sends the admins the "New expert account application" alert, as registration does. */
    private function alertAdminsAbout(User $applicant, bool $legacy = false): void
    {
        $details = ['applicant_id' => $applicant->id, 'applicant_name' => $applicant->name, 'organization' => $applicant->organization];
        if ($legacy) {
            unset($details['applicant_id']); // alerts sent before applicant_id was stored
        }

        Notification::sendNow(
            User::where('role', 'admin')->get(),
            new AdminAlert('New expert account application', 'Waiting for review.', 'expert_application',
                // Old alerts stored the full address of the moment, e.g. an expired tunnel URL.
                $legacy ? 'https://old-tunnel.trycloudflare.com/admin/users#expert-applications' : '/admin/users#expert-applications',
                'Review', details: $details),
            ['database'],
        );
    }

    public function test_reviewing_an_application_removes_the_admins_review_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $rejected = $this->applicant(['email' => 'rejected@example.com']);
        $approved = $this->applicant(['email' => 'approved@example.com']);
        $stillPending = $this->applicant(['email' => 'pending@example.com', 'name' => 'Still Pending']);
        $this->alertAdminsAbout($rejected);
        $this->alertAdminsAbout($approved, legacy: true);
        $this->alertAdminsAbout($stillPending);
        $this->assertSame(3, $admin->notifications()->count());

        $this->actingAs($admin)->post(route('admin.expert-applications.reject', $rejected))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.expert-applications.approve', $approved))->assertRedirect();

        $left = $admin->notifications()->get();
        $this->assertCount(1, $left);
        $this->assertSame($stillPending->id, $left->first()->data['applicant_id']);
    }

    public function test_deleting_an_applicant_removes_the_admins_review_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = $this->applicant();
        $this->alertAdminsAbout($applicant);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $applicant))->assertRedirect();

        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_rejected_applicant_sees_the_reapply_form_and_the_notice_links_to_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = $this->applicant();

        $this->actingAs($admin)->post(route('admin.expert-applications.reject', $applicant), [
            'rejection_notes' => 'Certificate is unreadable.',
        ]);

        $this->actingAs($applicant->fresh())
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Your last application was not approved')
            ->assertSee('Certificate is unreadable.')
            ->assertSee(route('profile.expert-application'), false);

        $this->actingAs($applicant->fresh())
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('href="/profile#expert-application"', false)
            ->assertSee('Reapply');
    }

    public function test_rejected_applicant_can_reapply_with_a_new_certificate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::disk('local')->put('expert-certificates/old.pdf', 'old');
        $applicant = $this->applicant([
            'expert_application_status' => User::EXPERT_APPLICATION_REJECTED,
            'expert_rejection_notes' => 'Certificate is unreadable.',
            'expert_reviewed_at' => now()->subDay(),
            'expert_reviewed_by' => $admin->id,
            'expert_certificate_path' => 'expert-certificates/old.pdf',
            'expert_certificate_name' => 'old.pdf',
        ]);
        $applicant->notifyNow(new ExpertApplicationReviewed(false, 'Certificate is unreadable.'), ['database']);

        $this->actingAs($applicant)
            ->post(route('profile.expert-application'), [
                'organization' => '  DENR CENRO Maasin ',
                'expert_qualifications' => 'Clear scan of my forester license attached.',
                'expert_certificate' => UploadedFile::fake()->create('license-clear.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success');

        $applicant->refresh();
        $this->assertSame('end_user', $applicant->role);
        $this->assertSame(User::EXPERT_APPLICATION_PENDING, $applicant->expert_application_status);
        $this->assertSame('DENR CENRO Maasin', $applicant->organization);
        $this->assertSame('license-clear.pdf', $applicant->expert_certificate_name);
        $this->assertNull($applicant->expert_rejection_notes);
        $this->assertNull($applicant->expert_reviewed_by);
        Storage::disk('local')->assertExists($applicant->expert_certificate_path);
        Storage::disk('local')->assertMissing('expert-certificates/old.pdf');

        // The old "not approved" notice is gone; admins get a fresh review notification.
        $this->assertSame(0, $applicant->notifications()->count());
        $alert = $admin->notifications()->first();
        $this->assertSame('Expert application resubmitted', $alert->data['title']);
        $this->assertSame($applicant->id, $alert->data['applicant_id']);

        // It is back in the admins' pending list and the profile shows it is waiting.
        $this->actingAs($admin)->get(route('admin.users'))->assertSee($applicant->name);
        $this->actingAs($applicant)->get(route('profile.show'))
            ->assertSee('Waiting for review')
            ->assertDontSee(route('profile.expert-application'), false);
    }

    public function test_reapplying_requires_a_certificate_and_organization(): void
    {
        $applicant = $this->applicant(['expert_application_status' => User::EXPERT_APPLICATION_REJECTED]);

        $this->actingAs($applicant)
            ->post(route('profile.expert-application'), ['organization' => ''])
            ->assertSessionHasErrorsIn('expertApplication', ['organization', 'expert_certificate']);

        $this->assertSame(User::EXPERT_APPLICATION_REJECTED, $applicant->fresh()->expert_application_status);
    }

    public function test_only_rejected_residents_can_reapply(): void
    {
        Notification::fake();
        $certificate = fn () => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf');
        $users = [
            $this->applicant(['email' => 'pending@example.com']), // still pending
            User::factory()->create(['role' => 'end_user']), // never applied
            User::factory()->create(['role' => 'expert', 'expert_application_status' => User::EXPERT_APPLICATION_REJECTED]),
        ];

        foreach ($users as $user) {
            $before = $user->expert_application_status;
            $this->actingAs($user)
                ->post(route('profile.expert-application'), ['organization' => 'SLSU', 'expert_certificate' => $certificate()])
                ->assertRedirect(route('profile.show'))
                ->assertSessionHas('error');
            $this->assertSame($before, $user->fresh()->expert_application_status);
        }

        Notification::assertNothingSent();
    }

    public function test_old_notification_links_open_on_the_current_site_address(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->alertAdminsAbout($this->applicant(), legacy: true);

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('href="/admin/users#expert-applications"', false)
            ->assertDontSee('old-tunnel.trycloudflare.com');
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

    public function test_expert_application_requires_organization(): void
    {
        $this->postJson('/register', $this->payload(['organization' => ' ']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['organization'])
            ->assertJsonMissingValidationErrors(['expert_qualifications']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_qualifications_are_optional_when_a_certificate_is_uploaded(): void
    {
        Event::fake([Registered::class]);
        Notification::fake();

        $this->postJson('/register', $this->payload(['expert_qualifications' => '']))->assertCreated();

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame(User::EXPERT_APPLICATION_PENDING, $user->expert_application_status);
        $this->assertNull($user->expert_qualifications);
        $this->assertNotNull($user->expert_certificate_path);
    }

    public function test_short_qualifications_note_is_accepted_with_certificate(): void
    {
        Event::fake([Registered::class]);
        Notification::fake();

        $this->postJson('/register', $this->payload(['expert_qualifications' => 'PhD, SLSU']))->assertCreated();

        $this->assertSame('PhD, SLSU', User::where('email', 'ana@example.com')->value('expert_qualifications'));
    }

    public function test_expert_application_stores_certificate_on_private_disk(): void
    {
        Storage::fake('local');
        Event::fake([Registered::class]);
        Notification::fake();

        $this->postJson('/register', $this->payload())->assertCreated();

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('forester-license.pdf', $user->expert_certificate_name);
        $this->assertStringStartsWith(User::EXPERT_CERTIFICATE_DIRECTORY . '/', $user->expert_certificate_path);
        Storage::disk('local')->assertExists($user->expert_certificate_path);
    }

    public function test_expert_application_requires_a_valid_certificate(): void
    {
        Storage::fake('local');

        $this->postJson('/register', $this->payload(['expert_certificate' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expert_certificate']);

        $this->postJson('/register', $this->payload([
            'expert_certificate' => UploadedFile::fake()->create('notes.docx', 50),
        ]))->assertStatus(422)->assertJsonValidationErrors(['expert_certificate']);

        $this->postJson('/register', $this->payload([
            'expert_certificate' => UploadedFile::fake()->create('big.pdf', User::EXPERT_CERTIFICATE_MAX_KB + 1, 'application/pdf'),
        ]))->assertStatus(422)->assertJsonValidationErrors(['expert_certificate']);

        $this->assertDatabaseCount('users', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_resident_registration_ignores_certificate(): void
    {
        Storage::fake('local');
        Event::fake([Registered::class]);
        Notification::fake();

        $this->postJson('/register', $this->payload(['apply_as_expert' => null]))->assertCreated();

        $this->assertNull(User::where('email', 'ana@example.com')->value('expert_certificate_path'));
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_only_admins_can_open_applicant_certificate(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('expert-certificates/proof.pdf', '%PDF-1.4 test');
        $applicant = $this->applicant([
            'expert_certificate_path' => 'expert-certificates/proof.pdf',
            'expert_certificate_name' => 'proof.pdf',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $url = route('admin.expert-applications.certificate', $applicant);

        $this->actingAs($admin)->get(route('admin.users'))->assertSee($url, false)->assertSee('proof.pdf');
        $this->actingAs($admin)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        foreach (['end_user', 'expert'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get($url)->assertRedirect($user->homeRoute());
        }
        $this->actingAs($applicant)->get($url)->assertRedirect($applicant->homeRoute());
    }

    public function test_user_details_modal_shows_application_and_certificate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = $this->applicant([
            'expert_certificate_path' => 'expert-certificates/proof.pdf',
            'expert_certificate_name' => 'proof.pdf',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $applicant), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('User Details')
            ->assertSee('Expert application')
            ->assertSee('Pending review')
            ->assertSee(route('admin.expert-applications.certificate', $applicant), false)
            ->assertSee('Marine biology lecturer studying mangrove genera.');
    }

    public function test_deleting_applicant_removes_certificate(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('expert-certificates/proof.pdf', '%PDF-1.4 test');
        $applicant = $this->applicant(['expert_certificate_path' => 'expert-certificates/proof.pdf']);

        $applicant->delete();

        Storage::disk('local')->assertMissing('expert-certificates/proof.pdf');
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

    public function test_approving_an_application_shows_the_shared_success_toast(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = $this->applicant(['name' => 'Pending Applicant']);

        $html = $this->actingAs($admin)
            ->followingRedirects()
            ->post(route('admin.expert-applications.approve', $applicant))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('window.AppActions?.notify("Pending Applicant is now an expert.")', $html);
        $this->assertStringNotContainsString('page-alert', $html);
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
