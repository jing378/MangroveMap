<?php

namespace Database\Seeders;

use App\Models\Delineation;
use App\Models\User;
use Illuminate\Database\Seeder;

class DelineationSeeder extends Seeder
{
    public function run(): void
    {
        $demo = User::where('email', 'demo@example.com')->first();
        $expert = User::where('email', 'expert@example.com')->first();

        if (!$demo || !$expert) {
            return;
        }

        $pendingFeatures = [
            [
                'type' => 'area',
                'coords' => [
                    [10.362, 125.008],
                    [10.368, 125.022],
                    [10.355, 125.018],
                ],
                'label' => 'Rhizophora',
            ],
        ];

        $approvedFeatures = [
            [
                'type' => 'area',
                'coords' => [
                    [10.312, 124.978],
                    [10.318, 124.988],
                    [10.305, 124.985],
                ],
                'label' => 'Avicennia',
            ],
            [
                'type' => 'line',
                'coords' => [
                    [10.314, 124.980],
                    [10.316, 124.986],
                ],
                'label' => 'Creek margin',
            ],
        ];

        $rejectedFeatures = [
            [
                'type' => 'area',
                'coords' => [
                    [10.400, 125.210],
                    [10.405, 125.220],
                    [10.392, 125.215],
                ],
                'label' => 'Xylocarpus',
            ],
        ];

        Delineation::updateOrCreate(
            ['user_id' => $demo->id, 'name' => 'Sogod Bay fringe'],
            [
                'notes' => 'Community-submitted polygon along the northern Sogod Bay fringe.',
                'features' => $pendingFeatures,
                'is_approved' => false,
                'is_rejected' => false,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_notes' => null,
            ]
        );

        Delineation::updateOrCreate(
            ['user_id' => $demo->id, 'name' => 'Limon Creek patch'],
            [
                'notes' => 'Verified mangrove patch near Limon Creek mouth.',
                'features' => $approvedFeatures,
                'is_approved' => true,
                'is_rejected' => false,
                'approved_at' => now()->subDays(3),
                'approved_by' => $expert->id,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_notes' => null,
            ]
        );

        Delineation::updateOrCreate(
            ['user_id' => $demo->id, 'name' => 'Hinunangan margin (rejected)'],
            [
                'notes' => 'Initial submission overlapped non-mangrove land cover.',
                'features' => $rejectedFeatures,
                'is_approved' => false,
                'is_rejected' => true,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => now()->subDays(1),
                'rejected_by' => $expert->id,
                'rejection_notes' => 'Polygon extends beyond verified mangrove extent. Please redraw within the tidal fringe.',
            ]
        );

        // Second approved delineation from demo (visible on community map)
        Delineation::updateOrCreate(
            ['user_id' => $demo->id, 'name' => 'Southern Coastal Belt zone'],
            [
                'notes' => 'Approved community delineation along the southern coastal belt.',
                'features' => [
                    [
                        'type' => 'area',
                        'coords' => [
                            [10.248, 124.748],
                            [10.255, 124.762],
                            [10.242, 124.758],
                        ],
                        'label' => 'Rhizophora',
                    ],
                ],
                'is_approved' => true,
                'is_rejected' => false,
                'approved_at' => now()->subWeek(),
                'approved_by' => $expert->id,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_notes' => null,
            ]
        );
    }
}
