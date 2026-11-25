<?php

namespace App\Console\Commands;

use App\Services\ExtentAccuracyReport;
use App\Services\MangroveComparison;
use App\Services\SatelliteMangroveGrid;
use App\Support\ExtentOverlap;
use Illuminate\Console\Command;

/**
 * Evidence for how accurate MangroveMap's mangrove areas are, for reports and the thesis manuscript:
 *   1. AI accuracy: each drone survey's AI draft against its reviewed boundary (ExtentAccuracyReport);
 *   2. satellite agreement of the expert-approved areas (Global Mangrove Watch);
 *   3. MangroveMap next to the official figures (config/mangrove_reference.php).
 * With --predicted and --reference it instead scores any two GeoJSON files (e.g. the AI's output for a site
 * against a boundary walked with GPS), plus the satellite agreement of the reference.
 */
class MangroveAccuracy extends Command
{
    protected $signature = 'mangroves:accuracy
        {--layer= : Only this drone imagery layer id}
        {--predicted= : GeoJSON of predicted mangrove areas (scores it against --reference instead)}
        {--reference= : GeoJSON of the reference (ground-truth) mangrove areas}
        {--year= : Satellite year for --reference (default: this year, or the latest the satellite has)}
        {--csv= : Also write the per-survey results as CSV to this path}
        {--markdown= : Also write the whole report as Markdown to this path}';

    protected $description = 'Score the AI mangrove areas against reviewed boundaries and compare MangroveMap with official figures';

    public function handle(MangroveComparison $comparison, SatelliteMangroveGrid $grid): int
    {
        if ($this->option('predicted') || $this->option('reference')) {
            return $this->compareFiles($grid);
        }

        $summary = $comparison->build();
        $cases = $summary['accuracy']['cases'];
        if ($this->option('layer') !== null) {
            $cases = array_values(array_filter($cases, fn (array $case) => $case['layer_id'] === (int) $this->option('layer')));
        }
        $markdown = [];

        // 1. AI accuracy
        $this->info('AI mangrove areas against the reviewed boundaries');
        $markdown[] = "## AI mangrove areas against the reviewed boundaries\n";
        if ($cases === []) {
            $this->line('  No drone survey has a reviewed boundary to compare with yet.');
            $markdown[] = "No drone survey has a reviewed boundary to compare with yet.\n";
        } else {
            $headers = ['Layer', 'Survey', 'Model', 'Reference', 'AI ha', 'Final ha', 'Overlap ha', 'IoU', 'Precision', 'Recall', 'F1'];
            $rows = array_map(fn (array $case) => [
                $case['layer_id'],
                $case['layer'],
                $case['model_label'],
                $case['reference_label'],
                $this->ha($case['predicted_ha']),
                $this->ha($case['reference_ha']),
                $this->ha($case['overlap_ha']),
                $this->score($case['iou']),
                $this->score($case['precision']),
                $this->score($case['recall']),
                $this->score($case['f1']),
            ], $cases);
            $this->table($headers, $rows);

            $own = app(ExtentAccuracyReport::class)->summary($cases);
            $pooled = $own['pooled'];
            $line = sprintf(
                'Pooled over %d %s: IoU %s, precision %s, recall %s, F1 %s (mean per survey: IoU %s, F1 %s). %d accepted unchanged.',
                $own['count'], $own['count'] === 1 ? 'survey' : 'surveys',
                $this->score($pooled['iou']), $this->score($pooled['precision']), $this->score($pooled['recall']), $this->score($pooled['f1']),
                $this->score($own['mean']['iou']), $this->score($own['mean']['f1']),
                $own['unchanged'],
            );
            $this->line('  '.$line);
            $markdown[] = $this->markdownTable($headers, $rows);
            $markdown[] = "\n{$line}\n";
        }
        $markdown[] = "Precision = share of the AI's area that was kept; recall = share of the final area the AI found; IoU = overlap / union.\n";

        // 2. Satellite agreement
        $this->newLine();
        $this->info('Expert-approved areas against the satellite (Global Mangrove Watch)');
        $markdown[] = "## Expert-approved areas against the satellite\n";
        $agreement = $summary['agreement'];
        if ($agreement === null) {
            $text = 'No approved areas to check (or the satellite file is missing).';
        } else {
            $text = sprintf(
                '%s of %s ha in %d approved %s was also mangrove in the satellite map (%s), year %s.',
                $this->percent($agreement['agreement']),
                $this->ha($agreement['area_ha']),
                $agreement['areas'],
                $agreement['areas'] === 1 ? 'area' : 'areas',
                $this->ha($agreement['satellite_ha']).' ha',
                implode(', ', $agreement['years']),
            );
        }
        $this->line('  '.$text);
        $markdown[] = "{$text}\n";

        // 3. Official figures
        $this->newLine();
        $this->info('MangroveMap compared with official figures');
        $markdown[] = "## MangroveMap compared with official figures\n";
        $officialRows = array_map(fn (array $figure) => [
            $figure['place'],
            number_format((float) $figure['hectares'], 2),
            $figure['year'],
            $figure['source'],
        ], $summary['references']);
        if ($summary['satellite'] !== null) {
            $officialRows[] = [$summary['satellite']['province'].' (satellite)', number_format($summary['satellite']['hectares'], 2), $summary['satellite']['year'], $summary['satellite']['source']];
            $officialRows[] = [$summary['satellite']['province'].' (satellite)', number_format($summary['satellite']['latest_hectares'], 2), $summary['satellite']['latest_year'], $summary['satellite']['source']];
        }
        $officialRows[] = ['MangroveMap, expert-approved', number_format($summary['system']['area_ha'], 2), now()->year, $summary['system']['area_count'].' approved areas'];
        $officialHeaders = ['Place', 'Hectares', 'Year', 'Source'];
        $this->table($officialHeaders, $officialRows);
        $markdown[] = $this->markdownTable($officialHeaders, $officialRows);

        if ($summary['municipalities'] !== []) {
            $satellite = $summary['satellite'];
            $placeHeaders = ['Municipality', 'Satellite '.($satellite['year'] ?? ''), 'Satellite '.($satellite['latest_year'] ?? ''), 'MangroveMap ha', 'Areas', '% mapped', 'Satellite agreement'];
            $placeRows = array_map(fn (array $row) => [
                $row['name'],
                $row['satellite_ha'] === null ? '—' : $this->ha($row['satellite_ha']),
                $row['satellite_latest_ha'] === null ? '—' : $this->ha($row['satellite_latest_ha']),
                $this->ha($row['system_ha']),
                $row['system_count'],
                $row['mapped_percent'] === null ? '—' : number_format($row['mapped_percent'], 1).'%',
                $this->percent($row['agreement']),
            ], $summary['municipalities']);
            $this->table($placeHeaders, $placeRows);
            $markdown[] = "\n".$this->markdownTable($placeHeaders, $placeRows);
        }

        if ($path = $this->option('csv')) {
            $this->writeCsv((string) $path, $cases);
        }
        if ($path = $this->option('markdown')) {
            $this->writeFile((string) $path, "# MangroveMap accuracy and comparison\n\nGenerated ".now()->toDateTimeString()."\n\n".implode("\n", $markdown));
        }

        return self::SUCCESS;
    }

    private function compareFiles(SatelliteMangroveGrid $grid): int
    {
        $reference = $this->readGeoJson('reference');
        if ($reference === null) {
            return self::FAILURE;
        }

        $markdown = [];
        if ($this->option('predicted')) {
            $predicted = $this->readGeoJson('predicted');
            if ($predicted === null) {
                return self::FAILURE;
            }
            $scores = ExtentOverlap::compare($predicted, $reference);
            $headers = ['Predicted ha', 'Reference ha', 'Overlap ha', 'IoU', 'Precision', 'Recall', 'F1'];
            $row = [[
                $this->ha($scores['predicted_ha']), $this->ha($scores['reference_ha']), $this->ha($scores['overlap_ha']),
                $this->score($scores['iou']), $this->score($scores['precision']), $this->score($scores['recall']), $this->score($scores['f1']),
            ]];
            $this->info('Predicted against reference');
            $this->table($headers, $row);
            $markdown[] = "## Predicted against reference\n\n".$this->markdownTable($headers, $row);
        }

        $year = (int) ($this->option('year') ?: now()->year);
        $satellite = $grid->agreement($reference, $year);
        $text = $satellite === null
            ? 'Satellite check unavailable (no satellite file, or the reference has no area).'
            : sprintf('%s of the reference (%s ha) was mangrove in the satellite map in %d.',
                $this->percent($satellite['agreement']), $this->ha($satellite['area_ha']), $satellite['year']);
        $this->newLine();
        $this->line($text);
        $markdown[] = "\n{$text}\n";

        if ($path = $this->option('markdown')) {
            $this->writeFile((string) $path, "# Mangrove area comparison\n\n".implode("\n", $markdown));
        }

        return self::SUCCESS;
    }

    private function readGeoJson(string $option): ?array
    {
        $path = (string) $this->option($option);
        if ($path === '' || ! is_file($path)) {
            $this->error("--{$option}: file not found ({$path}).");

            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        $shapes = is_array($data) ? ExtentOverlap::shapesFromGeoJson($data) : [];
        if ($shapes === []) {
            $this->error("--{$option}: no polygons found in {$path} (GeoJSON in longitude/latitude expected).");

            return null;
        }

        return $shapes;
    }

    private function writeCsv(string $path, array $cases): void
    {
        $columns = ['layer_id', 'layer', 'owner', 'model', 'reference', 'delineation_id', 'edited', 'predicted_ha', 'reference_ha', 'overlap_ha', 'union_ha', 'iou', 'precision', 'recall', 'f1'];
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns);
        foreach ($cases as $case) {
            fputcsv($handle, array_map(fn (string $column) => is_bool($case[$column]) ? ($case[$column] ? 'yes' : 'no') : $case[$column], $columns));
        }
        rewind($handle);
        $this->writeFile($path, (string) stream_get_contents($handle));
        fclose($handle);
    }

    private function writeFile(string $path, string $contents): void
    {
        file_put_contents($path, $contents);
        $this->line("Wrote {$path}");
    }

    private function markdownTable(array $headers, array $rows): string
    {
        $escape = fn ($value) => str_replace('|', '\\|', (string) $value);
        $lines = [
            '| '.implode(' | ', array_map($escape, $headers)).' |',
            '|'.str_repeat(' --- |', count($headers)),
        ];
        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', array_map($escape, $row)).' |';
        }

        return implode("\n", $lines)."\n";
    }

    private function ha(float $hectares): string
    {
        return number_format($hectares, $hectares < 100 ? 2 : 1);
    }

    private function score(?float $value): string
    {
        return $value === null ? '—' : number_format($value, 3);
    }

    private function percent(?float $share): string
    {
        return $share === null ? '—' : number_format($share * 100, 1).'%';
    }
}
