<?php

namespace Tests\Feature;

use App\Models\AIModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDeleteModelTest extends TestCase
{
    use RefreshDatabase;

    private function uploadedModel(array $attributes = []): AIModel
    {
        Storage::disk('local')->put('models/upload-test.pth', 'weights');

        return AIModel::create(array_merge([
            'name' => 'U-Net v2',
            'model_type' => 'segmentation',
            'version' => '2',
            'status' => 'completed',
            'model_path' => Storage::disk('local')->path('models/upload-test.pth'),
            'is_active' => false,
        ], $attributes));
    }

    public function test_admin_deletes_a_model_and_its_uploaded_file(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $model = $this->uploadedModel();

        $this->actingAs($admin)->get(route('admin.models'))
            ->assertOk()
            ->assertSee(route('admin.models.destroy', $model), false);

        $this->actingAs($admin)
            ->delete(route('admin.models.destroy', $model))
            ->assertRedirect(route('admin.models'))
            ->assertSessionHas('success', 'U-Net v2 was deleted.');

        $this->assertModelMissing($model);
        Storage::disk('local')->assertMissing('models/upload-test.pth');
        $this->assertDatabaseHas('user_activities', ['activity' => 'Deleted AI model: U-Net v2']);
    }

    public function test_deleting_the_active_model_says_the_default_file_is_used_next(): void
    {
        Storage::fake('local');
        $model = $this->uploadedModel(['is_active' => true, 'model_type' => 'classification', 'name' => 'YOLO v3']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('admin.models.destroy', $model))
            ->assertSessionHas('success', 'YOLO v3 was deleted. New AI runs use the default genus model file until you activate another one.');

        $this->assertModelMissing($model);
    }

    public function test_files_outside_the_upload_folder_are_never_deleted(): void
    {
        Storage::fake('local');
        // A record pointing at a file elsewhere (like the default weights in ml/models).
        $outside = tempnam(sys_get_temp_dir(), 'weights');
        $model = AIModel::create([
            'name' => 'Default weights', 'model_type' => 'segmentation', 'version' => '1',
            'status' => 'completed', 'model_path' => $outside, 'is_active' => false,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('admin.models.destroy', $model))
            ->assertSessionHas('success');

        $this->assertModelMissing($model);
        $this->assertFileExists($outside); // only the registry entry went
        @unlink($outside);
    }

    public function test_admin_deactivates_the_active_model_and_can_activate_it_again(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $model = $this->uploadedModel(['is_active' => true]);

        $this->actingAs($admin)->get(route('admin.models'))
            ->assertSee(route('admin.models.deactivate', $model), false);

        $this->actingAs($admin)
            ->post(route('admin.models.deactivate', $model))
            ->assertRedirect(route('admin.models'))
            ->assertSessionHas('success', 'U-Net v2 is no longer active. New AI runs use the default segmentation model file until you activate a model.');

        $this->assertFalse($model->fresh()->is_active);
        Storage::disk('local')->assertExists('models/upload-test.pth'); // kept, only deactivated
        $this->assertDatabaseHas('user_activities', ['activity' => 'Deactivated AI model: U-Net v2']);

        // The row now offers Activate again.
        $this->actingAs($admin)->get(route('admin.models'))
            ->assertSee(route('admin.models.activate', $model), false)
            ->assertDontSee(route('admin.models.deactivate', $model), false);
        $this->actingAs($admin)->post(route('admin.models.activate', $model))->assertSessionHas('success');
        $this->assertTrue($model->fresh()->is_active);
    }

    public function test_only_admins_can_delete_models(): void
    {
        Storage::fake('local');
        $model = $this->uploadedModel();

        foreach (['expert', 'end_user'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->delete(route('admin.models.destroy', $model))->assertRedirect($user->homeRoute());
            $this->actingAs($user)->post(route('admin.models.deactivate', $model))->assertRedirect($user->homeRoute());
        }

        $this->assertModelExists($model);
        Storage::disk('local')->assertExists('models/upload-test.pth');
    }
}
