<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Place names from OpenStreetMap (place=town/village/hamlet/...) for the labels on the satellite map.
 * Downloaded from the Overpass API for config('map.place_labels.bbox') and stored on the local disk, so the
 * map does not depend on Overpass being up. Each label has a rank: lower ranks show at lower zooms and win
 * when labels would overlap.
 */
class OsmPlaceLabelService
{
    /** OSM place type => rank (1 = most important). */
    public const PLACE_RANKS = [
        'city' => 1,
        'town' => 2,
        'island' => 3,
        'suburb' => 3,
        'village' => 3,
        'quarter' => 4,
        'hamlet' => 4,
        'neighbourhood' => 5,
        'locality' => 5,
        'isolated_dwelling' => 5,
        'islet' => 5,
    ];

    /**
     * Stored labels; downloads them the first time (one request at a time) if they were never synced.
     *
     * @return array<int, array{name: string, place: string, rank: int, lat: float, lng: float}>
     */
    public function labels(): array
    {
        $path = config('map.place_labels.storage_path');
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            try {
                Cache::lock('map-place-labels-sync', 120)->block(100, fn () => $disk->exists($path) || $this->sync());
            } catch (\Throwable $e) {
                Log::warning('Could not download OpenStreetMap place labels.', ['error' => $e->getMessage()]);

                return [];
            }
        }

        $data = json_decode((string) $disk->get($path), true);

        return is_array($data['labels'] ?? null) ? $data['labels'] : [];
    }

    /** Downloads the labels from Overpass and stores them. Returns how many were stored. */
    public function sync(): int
    {
        [$south, $west, $north, $east] = config('map.place_labels.bbox');
        $types = implode('|', array_keys(self::PLACE_RANKS));
        $timeout = config('map.place_labels.timeout');
        $query = "[out:json][timeout:{$timeout}];"
            . "node[\"place\"~\"^({$types})$\"][\"name\"]({$south},{$west},{$north},{$east});out body;";

        $response = Http::asForm()
            ->withHeaders(['User-Agent' => 'MangroveMap/1.0 (mangrove mapping web app)'])
            ->timeout($timeout + 15)
            ->post(config('map.place_labels.overpass_url'), ['data' => $query]);

        if (! $response->successful() || ! is_array($response->json('elements'))) {
            throw new RuntimeException('Overpass request failed with HTTP ' . $response->status() . '.');
        }

        $labels = collect($response->json('elements'))
            ->filter(fn ($node) => isset($node['lat'], $node['lon'], $node['tags']['name'], $node['tags']['place']))
            ->map(fn ($node) => [
                'name' => trim($node['tags']['name']),
                'place' => $node['tags']['place'],
                'rank' => self::PLACE_RANKS[$node['tags']['place']] ?? 5,
                'lat' => round((float) $node['lat'], 6),
                'lng' => round((float) $node['lon'], 6),
            ])
            ->filter(fn ($label) => $label['name'] !== '')
            // Most important first: the map keeps the first label of any overlapping group.
            ->sortBy([['rank', 'asc'], ['name', 'asc']])
            ->values()
            ->all();

        Storage::disk('local')->put(config('map.place_labels.storage_path'), json_encode([
            'source' => 'OpenStreetMap contributors (ODbL), via Overpass API',
            'synced_at' => now()->toIso8601String(),
            'bbox' => config('map.place_labels.bbox'),
            'labels' => $labels,
        ], JSON_UNESCAPED_UNICODE));

        return count($labels);
    }
}
