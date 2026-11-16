<?php

namespace App\Services;

/**
 * Hectares of mangrove per year from the Global Mangrove Watch file behind the map's
 * "Mangrove extent by year" (config map.mangrove_history.path), without the pixel data:
 * the province total and each municipality, for the dashboard chart.
 */
class MangroveHistoryStats
{
    /**
     * @return array{
     *     source: string,
     *     source_url: string|null,
     *     license: string|null,
     *     province: string,
     *     years: array<int, int>,
     *     total_ha: array<int, float>,
     *     municipalities: array<int, array{name: string, ha: array<int, float>}>,
     * }|null  Null when the file is missing or unreadable.
     */
    public function summary(): ?array
    {
        $path = config('map.mangrove_history.path');
        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $history = json_decode((string) file_get_contents($path), true);
        $years = is_array($history) ? ($history['years'] ?? null) : null;
        $total = is_array($history) ? ($history['total_ha'] ?? null) : null;
        if (! is_array($years) || $years === [] || ! is_array($total) || count($total) !== count($years)) {
            return null;
        }

        // Only places that had mangroves in some year, by name.
        $municipalities = collect($history['municipalities'] ?? [])
            ->filter(fn ($place) => is_array($place)
                && is_string($place['name'] ?? null)
                && is_array($place['ha'] ?? null)
                && count($place['ha']) === count($years)
                && max($place['ha']) > 0)
            ->map(fn (array $place) => ['name' => $place['name'], 'ha' => array_values($place['ha'])])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'source' => (string) ($history['source'] ?? 'Global Mangrove Watch'),
            'source_url' => $history['source_url'] ?? null,
            'license' => $history['license'] ?? null,
            'province' => (string) ($history['province'] ?? 'Whole province'),
            'years' => array_values($years),
            'total_ha' => array_values($total),
            'municipalities' => $municipalities,
        ];
    }
}
