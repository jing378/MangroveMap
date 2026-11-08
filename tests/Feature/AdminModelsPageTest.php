<?php

namespace Tests\Feature;

use App\Models\AIModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModelsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_use_the_shared_toast_instead_of_an_inline_box(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['success' => 'Performance figures for U-Net v3 saved.'])
            ->get(route('admin.models'))
            ->assertOk()
            ->assertSee('window.AppActions?.notify("Performance figures for U-Net v3 saved.")', false)
            ->assertDontSee('class="upload-status', false);

        $this->actingAs($admin)
            ->withSession(['error' => 'The model file could not be read.'])
            ->get(route('admin.models'))
            ->assertOk()
            ->assertSee('window.AppActions?.notify("The model file could not be read.", \'error\')', false);
    }

    public function test_registry_rows_carry_labels_for_the_phone_card_layout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AIModel::create([
            'name' => 'U-Net v3',
            'version' => '3.0.0',
            'model_type' => 'segmentation',
            'status' => 'completed',
            'model_path' => 'models/missing.pth',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.models'))
            ->assertOk()
            ->assertSee('class="table registry-table"', false)
            ->assertSee('<tr class="registry-row">', false)
            ->assertSee('data-label="Type"', false)
            ->assertSee('data-label="Status"', false)
            ->assertSee('data-label="Checkpoint"', false)
            ->assertSee('data-label="Accuracy"', false)
            ->assertSee('data-label="Date added"', false);
    }
}
