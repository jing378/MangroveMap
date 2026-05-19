<?php

namespace Database\Seeders;

use App\Models\Genus;
use Illuminate\Database\Seeder;

class GenusSeeder extends Seeder
{
    public function run(): void
    {
        $genera = [
            [
                'common_name' => 'Bakawan',
                'scientific_name' => 'Rhizophora apiculata',
                'genus' => 'Rhizophora',
                'family' => 'Rhizophoraceae',
                'description' => 'True mangrove with stilt roots; common along sheltered coasts.',
                'conservation_status' => 'Least Concern',
                'geographical_distribution' => 'Southern Leyte coast, Sogod Bay, Limon Bay',
                'salinity_tolerance' => 'euryhaline',
            ],
            [
                'common_name' => 'Bungalon',
                'scientific_name' => 'Avicennia marina',
                'genus' => 'Avicennia',
                'family' => 'Acanthaceae',
                'description' => 'Gray mangrove with pneumatophores; tolerates high salinity.',
                'conservation_status' => 'Least Concern',
                'geographical_distribution' => 'Estuarine margins, Southern Coastal Belt',
                'salinity_tolerance' => 'high',
            ],
            [
                'common_name' => 'Pagatpat',
                'scientific_name' => 'Sonneratia alba',
                'genus' => 'Sonneratia',
                'family' => 'Lythraceae',
                'description' => 'Pioneer species on muddy shores with buttress roots.',
                'conservation_status' => 'Least Concern',
                'geographical_distribution' => 'Saint Bernard wetlands, Hinunangan',
                'salinity_tolerance' => 'moderate',
            ],
            [
                'common_name' => 'Miapi',
                'scientific_name' => 'Bruguiera gymnorrhiza',
                'genus' => 'Bruguiera',
                'family' => 'Rhizophoraceae',
                'description' => 'Knee-rooted mangrove in interior tidal zones.',
                'conservation_status' => 'Least Concern',
                'geographical_distribution' => 'Inland fringes of Sogod Bay mangrove stands',
                'salinity_tolerance' => 'moderate',
            ],
            [
                'common_name' => 'Tabigi',
                'scientific_name' => 'Xylocarpus granatum',
                'genus' => 'Xylocarpus',
                'family' => 'Meliaceae',
                'description' => 'Large-fruited mangrove on higher intertidal ground.',
                'conservation_status' => 'Vulnerable',
                'geographical_distribution' => 'Scattered patches near Hinunangan',
                'salinity_tolerance' => 'low to moderate',
            ],
            [
                'common_name' => 'Tungog',
                'scientific_name' => 'Ceriops tagal',
                'genus' => 'Ceriops',
                'family' => 'Rhizophoraceae',
                'description' => 'Compact mangrove forming dense understory stands.',
                'conservation_status' => 'Least Concern',
                'geographical_distribution' => 'Limon Bay and adjacent creeks',
                'salinity_tolerance' => 'moderate',
            ],
        ];

        foreach ($genera as $genus) {
            Genus::updateOrCreate(
                ['scientific_name' => $genus['scientific_name']],
                $genus
            );
        }
    }
}
