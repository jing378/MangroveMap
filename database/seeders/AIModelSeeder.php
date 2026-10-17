<?php

namespace Database\Seeders;

use App\Models\AIModel;
use Illuminate\Database\Seeder;

class AIModelSeeder extends Seeder
{
    public function run(): void
    {
        $checkpointPath = config('ml.model_path');
        if (! is_string($checkpointPath) || ! is_file($checkpointPath)) {
            return;
        }

        AIModel::firstOrCreate(
            ['model_path' => $checkpointPath],
            [
                'name' => 'Mangrove U-Net (Configured)',
                'model_type' => 'segmentation',
                'version' => 'configured',
                'accuracy' => null,
                'training_date' => null,
                'dataset_size' => null,
                'status' => 'completed',
                'description' => 'Configured segmentation checkpoint found on disk.',
                'is_active' => ! AIModel::query()
                    ->where('model_type', 'segmentation')
                    ->where('is_active', true)
                    ->exists(),
            ],
        );
    }
}
