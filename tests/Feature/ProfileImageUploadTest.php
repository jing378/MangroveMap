<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $user, array $overrides = []): array
    {
        // Mirrors the profile page's fetch(): non-admins have no organization field.
        return array_merge([
            '_method' => 'PUT',
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => '09123456789',
        ], $overrides);
    }

    public function test_resident_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)
            ->postJson(route('profile.update'), $this->payload($resident, [
                'profile_image' => UploadedFile::fake()->image('me.jpg', 300, 300),
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'Your profile was updated.')
            ->assertJsonPath('name', $resident->name);

        $path = $resident->fresh()->profile_image;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('profiles/', $path);
        Storage::disk('public')->assertExists($path);

        $this->get(route('profile.show'))->assertSee('storage/' . $path);
    }

    public function test_webp_photos_are_accepted(): void
    {
        Storage::fake('public');
        $expert = User::factory()->create(['role' => 'expert', 'organization' => 'SLSU']);

        $this->actingAs($expert)
            ->postJson(route('profile.update'), $this->payload($expert, [
                'organization' => 'SLSU',
                'profile_image' => UploadedFile::fake()->image('me.webp'),
            ]))
            ->assertOk();

        Storage::disk('public')->assertExists($expert->fresh()->profile_image);
    }

    public function test_new_photo_replaces_the_old_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($user)->postJson(route('profile.update'), $this->payload($user, [
            'profile_image' => UploadedFile::fake()->image('first.png'),
        ]))->assertOk();
        $oldPath = $user->fresh()->profile_image;

        $this->postJson(route('profile.update'), $this->payload($user, [
            'profile_image' => UploadedFile::fake()->image('second.png'),
        ]))->assertOk();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->fresh()->profile_image);
    }

    public function test_oversized_or_non_image_files_are_rejected_with_json_errors(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'end_user']);
        $this->actingAs($user);

        $this->postJson(route('profile.update'), $this->payload($user, [
            'profile_image' => UploadedFile::fake()->image('big.jpg')->size(3000),
        ]))->assertStatus(422)
            ->assertJsonPath('errors.profile_image.0', 'The profile photo must be 2 MB or smaller.');

        $this->postJson(route('profile.update'), $this->payload($user, [
            'profile_image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('profile_image');

        $this->assertNull($user->fresh()->profile_image);
    }

    public function test_profile_page_script_does_not_require_the_admin_only_organization_field(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)->get(route('profile.show'))
            ->assertOk()
            ->assertDontSee('id="organization"', false)
            ->assertDontSee("getElementById('organization').value", false)
            ->assertSee("'Accept': 'application/json'", false);
    }

    public function test_profile_success_uses_the_shared_toast_instead_of_a_banner(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)
            ->withSession(['success' => 'Password updated successfully!'])
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee("successMessage: payload => payload?.message || 'Your profile was updated.'", false)
            ->assertSee('window.AppActions?.notify("Password updated successfully!")', false)
            ->assertDontSee('<div class="alert alert-success">', false)
            ->assertDontSee('window.location.reload()', false);
    }

    public function test_photo_preview_offers_crop_and_zoom_controls(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Adjust Profile Photo')
            ->assertSee('id="crop-stage"', false)
            ->assertSee('id="crop-zoom"', false)
            ->assertSee('id="crop-reset"', false)
            ->assertSee('function cropToBlob()', false);
    }
}
