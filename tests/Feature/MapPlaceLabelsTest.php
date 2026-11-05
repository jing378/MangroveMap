<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** OpenStreetMap place names for the satellite map labels (downloaded from Overpass, served from storage). */
class MapPlaceLabelsTest extends TestCase
{
    private function fakeOverpass(): void
    {
        Http::fake([
            'overpass-api.de/*' => Http::response(['elements' => [
                ['type' => 'node', 'lat' => 10.3561, 'lon' => 124.9693, 'tags' => ['place' => 'village', 'name' => 'Divisoria']],
                ['type' => 'node', 'lat' => 10.3582, 'lon' => 124.9711, 'tags' => ['place' => 'town', 'name' => 'Bontoc']],
                ['type' => 'node', 'lat' => 10.3600, 'lon' => 124.9700, 'tags' => ['place' => 'hamlet', 'name' => 'Lum-an']],
                ['type' => 'node', 'lat' => 10.3600, 'lon' => 124.9700, 'tags' => ['place' => 'village']], // no name: skipped
            ]]),
        ]);
    }

    public function test_sync_command_stores_osm_places_most_important_first(): void
    {
        Storage::fake('local');
        $this->fakeOverpass();

        $this->artisan('map:sync-place-labels')
            ->expectsOutput('Stored 3 OpenStreetMap place names.')
            ->assertSuccessful();

        $stored = json_decode(Storage::disk('local')->get(config('map.place_labels.storage_path')), true);
        $this->assertSame(['Bontoc', 'Divisoria', 'Lum-an'], array_column($stored['labels'], 'name'));
        $this->assertSame([2, 3, 4], array_column($stored['labels'], 'rank'));
        Http::assertSent(fn ($request) => str_contains($request['data'], 'node["place"~"^(city|town|'));
    }

    public function test_endpoint_downloads_once_and_then_serves_the_stored_places_to_guests(): void
    {
        Storage::fake('local');
        $this->fakeOverpass();

        $this->getJson(route('map.place-labels'))
            ->assertOk()
            ->assertJsonCount(3, 'places')
            ->assertJsonPath('places.0', ['name' => 'Bontoc', 'place' => 'town', 'rank' => 2, 'lat' => 10.3582, 'lng' => 124.9711]);

        $this->getJson(route('map.place-labels'))->assertOk()->assertJsonCount(3, 'places');
        Http::assertSentCount(1);
    }

    public function test_endpoint_returns_no_places_when_overpass_is_down(): void
    {
        Storage::fake('local');
        Http::fake(['overpass-api.de/*' => Http::response('busy', 504)]);

        $this->getJson(route('map.place-labels'))
            ->assertOk()
            ->assertExactJson(['places' => []]);
    }
}
