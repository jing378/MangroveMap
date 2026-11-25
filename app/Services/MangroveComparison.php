<?php

namespace App\Services;

use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Support\ExtentOverlap;
use Illuminate\Support\Facades\Cache;

/**
 * "Compared with official figures" on the dashboards: MangroveMap's expert-approved hectares next to the
 * official totals (config/mangrove_reference.php: PSA national and regional, the provincial office) and the
 * Global Mangrove Watch satellite hectares per municipality, plus two checks of the mapped areas:
 *   - satellite agreement: the share of approved areas the satellite also saw as mangrove (in the year each
 *     was approved, or the nearest year it has);
 *   - AI accuracy: ExtentAccuracyReport's scores of the AI drafts against the reviewed boundaries.
 * Cached briefly; the key changes whenever a delineation or a drone layer changes.
 */
class MangroveComparison
{
    private const CACHE_SECONDS = 600;

    public function __construct(
        private ApprovedCoverageStats $coverage,
        private MangroveHistoryStats $history,
        private SatelliteMangroveGrid $grid,
        private ExtentAccuracyReport $accuracy,
    ) {}

    public function summary(): array
    {
        $version = implode('|', [
            Delineation::count(),
            (string) Delineation::max('updated_at'),
            DroneImageryLayer::count(),
            (string) DroneImageryLayer::max('updated_at'),
            md5(json_encode(config('mangrove_reference'))),
        ]);

        return Cache::remember('mangrove-comparison:'.md5($version), self::CACHE_SECONDS, fn () => $this->build());
    }

    public function build(): array
    {
        $references = array_values(array_filter(
            (array) config('mangrove_reference.figures', []),
            fn ($figure) => is_array($figure) && is_numeric($figure['hectares'] ?? null),
        ));
        $provinceKey = config('mangrove_reference.province');
        $provinceFigure = collect($references)->firstWhere('key', $provinceKey);

        $coverage = $this->coverage->summary();
        $history = $this->history->summary();
        $satellite = $this->satelliteTotals($history);
        $agreement = $this->approvedAgreement();

        $accuracyCases = $this->accuracy->cases();

        return [
            'references' => $references,
            'province' => $provinceFigure,
            'satellite' => $satellite,
            'system' => [
                'area_ha' => (float) $coverage['area_ha'],
                'area_count' => (int) $coverage['area_count'],
                'share_of_province' => $provinceFigure && $provinceFigure['hectares'] > 0
                    ? round($coverage['area_ha'] / $provinceFigure['hectares'] * 100, 2)
                    : null,
            ],
            'municipalities' => $this->municipalities($history, $satellite, $coverage['by_municipality'], $agreement['by_municipality']),
            'agreement' => $agreement['total'],
            'accuracy' => [
                ...$this->accuracy->summary($accuracyCases),
                'cases' => $accuracyCases,
            ],
        ];
    }

    /** Satellite totals for the province in the reference year and the latest year, or null without the file. */
    private function satelliteTotals(?array $history): ?array
    {
        if ($history === null) {
            return null;
        }
        $years = $history['years'];
        $year = (int) config('mangrove_reference.satellite_year', 2020);
        $yearIndex = array_search($year, $years, true);
        if ($yearIndex === false) {
            $yearIndex = count($years) - 1;
            $year = (int) end($years);
        }
        $latestIndex = count($years) - 1;

        return [
            'source' => $history['source'],
            'source_url' => $history['source_url'],
            'license' => $history['license'],
            'province' => $history['province'],
            'year' => $year,
            'year_index' => $yearIndex,
            'latest_year' => (int) $years[$latestIndex],
            'latest_index' => $latestIndex,
            'hectares' => (float) $history['total_ha'][$yearIndex],
            'latest_hectares' => (float) $history['total_ha'][$latestIndex],
        ];
    }

    /**
     * One row per municipality the satellite or the system has mangroves in, by satellite hectares (latest year).
     *
     * @return array<int, array{name: string, satellite_ha: ?float, satellite_latest_ha: ?float, system_ha: float, system_count: int, mapped_percent: ?float, agreement: ?float}>
     */
    private function municipalities(?array $history, ?array $satellite, array $byMunicipality, array $agreement): array
    {
        $rows = [];
        foreach ($history['municipalities'] ?? [] as $place) {
            $rows[$place['name']] = [
                'name' => $place['name'],
                'satellite_ha' => (float) $place['ha'][$satellite['year_index']],
                'satellite_latest_ha' => (float) $place['ha'][$satellite['latest_index']],
                'system_ha' => 0.0,
                'system_count' => 0,
            ];
        }
        foreach ($byMunicipality as $row) {
            if ($row['name'] === null) {
                continue;
            }
            $rows[$row['name']] ??= [
                'name' => $row['name'],
                'satellite_ha' => $satellite ? 0.0 : null,
                'satellite_latest_ha' => $satellite ? 0.0 : null,
                'system_ha' => 0.0,
                'system_count' => 0,
            ];
            $rows[$row['name']]['system_ha'] = (float) $row['area_ha'];
            $rows[$row['name']]['system_count'] = (int) $row['area_count'];
        }

        $rows = array_map(fn (array $row) => [
            ...$row,
            'mapped_percent' => ($row['satellite_latest_ha'] ?? 0) > 0
                ? round($row['system_ha'] / $row['satellite_latest_ha'] * 100, 1)
                : null,
            'agreement' => $agreement[$row['name']]['agreement'] ?? null,
        ], array_values($rows));

        usort($rows, fn (array $a, array $b) => [$b['satellite_latest_ha'] ?? 0, $b['system_ha']] <=> [$a['satellite_latest_ha'] ?? 0, $a['system_ha']]
            ?: strnatcasecmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * Share of the approved areas the satellite also saw as mangrove, in total and per municipality.
     *
     * @return array{total: ?array{area_ha: float, satellite_ha: float, agreement: ?float, areas: int, years: array<int, int>}, by_municipality: array<string, array{area_ha: float, satellite_ha: float, agreement: ?float}>}
     */
    private function approvedAgreement(): array
    {
        if (! $this->grid->available()) {
            return ['total' => null, 'by_municipality' => []];
        }

        $delineations = Delineation::approved()
            ->where('is_rejected', false)
            ->withoutAppliedReassessments()
            ->get(['id', 'features', 'created_at', 'approved_at']);

        $total = ['area_ha' => 0.0, 'satellite_ha' => 0.0, 'areas' => 0, 'years' => []];
        $places = [];
        foreach ($delineations as $delineation) {
            $features = is_array($delineation->features) ? $delineation->features : [];
            $year = ($delineation->approved_at ?? $delineation->created_at ?? now())->year;
            $result = $this->grid->agreement(ExtentOverlap::shapesFromFeatures($features), $year);
            if ($result === null || $result['area_ha'] <= 0) {
                continue;
            }
            $total['area_ha'] += $result['area_ha'];
            $total['satellite_ha'] += $result['satellite_ha'];
            $total['areas']++;
            $total['years'][$result['year']] = true;

            $name = ApprovedCoverageStats::municipalityName($features);
            if ($name !== null) {
                $places[$name]['area_ha'] = ($places[$name]['area_ha'] ?? 0) + $result['area_ha'];
                $places[$name]['satellite_ha'] = ($places[$name]['satellite_ha'] ?? 0) + $result['satellite_ha'];
            }
        }

        $finish = fn (array $sums) => [
            ...$sums,
            'area_ha' => round($sums['area_ha'], 2),
            'satellite_ha' => round($sums['satellite_ha'], 2),
            'agreement' => $sums['area_ha'] > 0 ? round($sums['satellite_ha'] / $sums['area_ha'], 4) : null,
        ];

        $years = array_keys($total['years']);
        sort($years);

        return [
            'total' => $total['areas'] > 0 ? [...$finish($total), 'years' => $years] : null,
            'by_municipality' => array_map($finish, $places),
        ];
    }
}
