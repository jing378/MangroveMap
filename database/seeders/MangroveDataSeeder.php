<?php

namespace Database\Seeders;

use App\Models\Genus;
use App\Models\MangroveData;
use Illuminate\Database\Seeder;

class MangroveDataSeeder extends Seeder
{
    public function run(): void
    {
        $genusIds = Genus::pluck('id', 'genus');

        $records = [
            // Sogod Bay
            ['region' => 'Sogod Bay', 'latitude' => 10.3820, 'longitude' => 125.0180, 'coverage_area_km2' => 2.45, 'genus' => 'Rhizophora', 'health_status' => 'healthy', 'degradation_level' => 12, 'observation_date' => '2025-06-15', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.92],
            ['region' => 'Sogod Bay', 'latitude' => 10.3750, 'longitude' => 125.0320, 'coverage_area_km2' => 1.80, 'genus' => 'Bruguiera', 'health_status' => 'healthy', 'degradation_level' => 18, 'observation_date' => '2025-06-15', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.89],
            ['region' => 'Sogod Bay', 'latitude' => 10.3680, 'longitude' => 125.0050, 'coverage_area_km2' => 0.95, 'genus' => 'Rhizophora', 'health_status' => 'recovering', 'degradation_level' => 35, 'observation_date' => '2024-08-20', 'data_source' => 'Field survey', 'confidence_score' => 0.87],
            ['region' => 'Sogod Bay', 'latitude' => 10.3910, 'longitude' => 125.0410, 'coverage_area_km2' => 1.22, 'genus' => 'Sonneratia', 'health_status' => 'healthy', 'degradation_level' => 10, 'observation_date' => '2023-11-05', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.91],
            // Limon Bay
            ['region' => 'Limon Bay', 'latitude' => 10.3180, 'longitude' => 124.9820, 'coverage_area_km2' => 1.12, 'genus' => 'Avicennia', 'health_status' => 'degraded', 'degradation_level' => 68, 'observation_date' => '2025-06-15', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.86],
            ['region' => 'Limon Bay', 'latitude' => 10.3050, 'longitude' => 124.9680, 'coverage_area_km2' => 0.74, 'genus' => 'Ceriops', 'health_status' => 'degraded', 'degradation_level' => 72, 'observation_date' => '2025-03-10', 'data_source' => 'Field survey', 'confidence_score' => 0.84],
            ['region' => 'Limon Bay', 'latitude' => 10.3280, 'longitude' => 124.9950, 'coverage_area_km2' => 0.58, 'genus' => 'Avicennia', 'health_status' => 'recovering', 'degradation_level' => 42, 'observation_date' => '2024-05-18', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.88],
            // Saint Bernard
            ['region' => 'Saint Bernard', 'latitude' => 10.2780, 'longitude' => 125.1520, 'coverage_area_km2' => 0.87, 'genus' => 'Sonneratia', 'health_status' => 'recovering', 'degradation_level' => 38, 'observation_date' => '2025-03-20', 'data_source' => 'Field survey', 'confidence_score' => 0.85],
            ['region' => 'Saint Bernard', 'latitude' => 10.2650, 'longitude' => 125.1380, 'coverage_area_km2' => 1.05, 'genus' => 'Rhizophora', 'health_status' => 'healthy', 'degradation_level' => 15, 'observation_date' => '2024-12-01', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.90],
            ['region' => 'Saint Bernard', 'latitude' => 10.2920, 'longitude' => 125.1650, 'coverage_area_km2' => 0.63, 'genus' => 'Bruguiera', 'health_status' => 'healthy', 'degradation_level' => 20, 'observation_date' => '2023-09-14', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.88],
            // Hinunangan
            ['region' => 'Hinunangan', 'latitude' => 10.4020, 'longitude' => 125.2180, 'coverage_area_km2' => 3.10, 'genus' => 'Rhizophora', 'health_status' => 'healthy', 'degradation_level' => 8, 'observation_date' => '2024-11-10', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.93],
            ['region' => 'Hinunangan', 'latitude' => 10.4150, 'longitude' => 125.2350, 'coverage_area_km2' => 1.45, 'genus' => 'Xylocarpus', 'health_status' => 'healthy', 'degradation_level' => 14, 'observation_date' => '2025-01-22', 'data_source' => 'Field survey', 'confidence_score' => 0.87],
            ['region' => 'Hinunangan', 'latitude' => 10.3880, 'longitude' => 125.2050, 'coverage_area_km2' => 0.92, 'genus' => 'Sonneratia', 'health_status' => 'recovering', 'degradation_level' => 30, 'observation_date' => '2023-06-30', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.86],
            // Southern Coastal Belt
            ['region' => 'Southern Coastal Belt', 'latitude' => 10.2520, 'longitude' => 124.7520, 'coverage_area_km2' => 4.20, 'genus' => 'Rhizophora', 'health_status' => 'healthy', 'degradation_level' => 11, 'observation_date' => '2025-01-08', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.94],
            ['region' => 'Southern Coastal Belt', 'latitude' => 10.2380, 'longitude' => 124.7680, 'coverage_area_km2' => 2.10, 'genus' => 'Avicennia', 'health_status' => 'healthy', 'degradation_level' => 16, 'observation_date' => '2024-04-12', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.91],
            ['region' => 'Southern Coastal Belt', 'latitude' => 10.2680, 'longitude' => 124.7410, 'coverage_area_km2' => 1.35, 'genus' => 'Bruguiera', 'health_status' => 'degraded', 'degradation_level' => 55, 'observation_date' => '2023-02-28', 'data_source' => 'Field survey', 'confidence_score' => 0.83],
            // Additional scatter for map density
            ['region' => 'Sogod Bay', 'latitude' => 10.3600, 'longitude' => 125.0250, 'coverage_area_km2' => 0.48, 'genus' => 'Ceriops', 'health_status' => 'healthy', 'degradation_level' => 22, 'observation_date' => '2025-06-15', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.88],
            ['region' => 'Limon Bay', 'latitude' => 10.3120, 'longitude' => 124.9900, 'coverage_area_km2' => 0.41, 'genus' => 'Rhizophora', 'health_status' => 'recovering', 'degradation_level' => 40, 'observation_date' => '2024-10-05', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.85],
            ['region' => 'Hinunangan', 'latitude' => 10.3980, 'longitude' => 125.2280, 'coverage_area_km2' => 0.67, 'genus' => 'Avicennia', 'health_status' => 'degraded', 'degradation_level' => 60, 'observation_date' => '2024-07-19', 'data_source' => 'Field survey', 'confidence_score' => 0.82],
            ['region' => 'Southern Coastal Belt', 'latitude' => 10.2450, 'longitude' => 124.7800, 'coverage_area_km2' => 0.89, 'genus' => 'Sonneratia', 'health_status' => 'healthy', 'degradation_level' => 13, 'observation_date' => '2025-06-15', 'data_source' => 'Sentinel-2', 'confidence_score' => 0.90],
        ];

        foreach ($records as $record) {
            $genusKey = $record['genus'];
            unset($record['genus']);

            $record['genus_id'] = $genusIds[$genusKey] ?? null;
            $record['observation_date'] = $record['observation_date'] . ' 00:00:00';

            MangroveData::create($record);
        }
    }
}
