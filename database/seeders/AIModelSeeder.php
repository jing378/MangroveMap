<?php

namespace Database\Seeders;

use App\Models\AIModel;
use Illuminate\Database\Seeder;

class AIModelSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            [
                'name' => 'MangroveGenus-v1',
                'model_type' => 'classification',
                'version' => '1.0.0',
                'accuracy' => 0.8912,
                'training_date' => '2025-04-10',
                'dataset_size' => 12450,
                'status' => 'completed',
                'description' => 'Genus classification model trained on Southern Leyte Sentinel-2 patches.',
                'model_path' => 'models/mangrove-genus-v1.onnx',
                'is_active' => true,
            ],
            [
                'name' => 'CoastSegment-v1',
                'model_type' => 'segmentation',
                'version' => '1.0.0',
                'accuracy' => 0.8435,
                'training_date' => '2025-03-22',
                'dataset_size' => 8900,
                'status' => 'completed',
                'description' => 'Coastal mangrove extent segmentation for delineation assist.',
                'model_path' => 'models/coast-segment-v1.onnx',
                'is_active' => false,
            ],
            [
                'name' => 'ChangeDetect-v1',
                'model_type' => 'change_detection',
                'version' => '0.9.2',
                'accuracy' => 0.8120,
                'training_date' => '2025-05-01',
                'dataset_size' => 6200,
                'status' => 'training',
                'description' => 'Bi-temporal change detection (2023–2025 stack).',
                'model_path' => null,
                'is_active' => false,
            ],
        ];

        foreach ($models as $model) {
            AIModel::updateOrCreate(
                ['name' => $model['name']],
                $model
            );
        }
    }
}
