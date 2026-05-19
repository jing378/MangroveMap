<?php

namespace Database\Seeders;

use App\Models\Analysis;
use App\Models\MangroveData;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnalysisSeeder extends Seeder
{
    public function run(): void
    {
        $demo = User::where('email', 'demo@example.com')->first();

        if (!$demo) {
            return;
        }

        $sogodPoint = MangroveData::where('region', 'Sogod Bay')->first();
        $limonPoint = MangroveData::where('region', 'Limon Bay')->where('health_status', 'degraded')->first();

        $analyses = [
            [
                'user_id' => $demo->id,
                'mangrove_data_id' => $sogodPoint?->id,
                'analysis_type' => 'classification',
                'species_detected' => 'Rhizophora',
                'classification_confidence' => 0.91,
                'detected_damage' => false,
                'recommendations' => 'Dominant Rhizophora stand appears healthy. Continue routine monitoring every 6 months.',
                'status' => 'completed',
                'results' => [
                    'top_classes' => [
                        ['label' => 'Rhizophora', 'confidence' => 0.91],
                        ['label' => 'Bruguiera', 'confidence' => 0.06],
                    ],
                ],
                'created_at' => now()->subDays(5),
            ],
            [
                'user_id' => $demo->id,
                'mangrove_data_id' => $limonPoint?->id,
                'analysis_type' => 'change_detection',
                'species_detected' => null,
                'classification_confidence' => null,
                'detected_damage' => true,
                'recommendations' => 'Coverage loss of ~12% detected vs. 2024 baseline. Recommend field validation and sediment trapping assessment.',
                'status' => 'completed',
                'results' => [
                    'change_percent' => -12.4,
                    'period' => '2024-2025',
                ],
                'created_at' => now()->subDays(12),
            ],
            [
                'user_id' => $demo->id,
                'mangrove_data_id' => $limonPoint?->id,
                'analysis_type' => 'damage_assessment',
                'species_detected' => 'Avicennia',
                'classification_confidence' => 0.78,
                'detected_damage' => true,
                'recommendations' => null,
                'status' => 'pending',
                'results' => null,
                'created_at' => now()->subHours(6),
            ],
            [
                'user_id' => $demo->id,
                'mangrove_data_id' => null,
                'analysis_type' => 'classification',
                'species_detected' => null,
                'classification_confidence' => null,
                'detected_damage' => false,
                'recommendations' => null,
                'status' => 'failed',
                'results' => ['error' => 'Image resolution below minimum threshold.'],
                'created_at' => now()->subDays(20),
            ],
        ];

        foreach ($analyses as $analysis) {
            Analysis::create($analysis);
        }
    }
}
