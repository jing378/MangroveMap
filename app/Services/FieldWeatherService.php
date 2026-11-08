<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Short weather forecast for field work (surveys, planting) from Open-Meteo: free, no API key.
 * Given for the viewer's location when the browser shares it, else for the default place (config
 * map.weather). Coordinates are rounded to about 1 km, so nearby users share one cached forecast
 * (config map.weather.cache_minutes) and no exact position is kept; a failed request is remembered
 * briefly so an offline API is not asked again on every dashboard view.
 */
class FieldWeatherService
{
    private const CACHE_KEY = 'map.field-weather.v2';

    /** Decimal places kept from the viewer's coordinates (0.01° is about 1.1 km). */
    private const COORDINATE_PRECISION = 2;

    public function __construct(private readonly BoundaryResolver $boundaries)
    {
    }

    /** Field-work rating thresholds (rain in mm/day, wind gusts in km/h). */
    private const AVOID_GUST_KMH = 60;

    private const AVOID_RAIN_MM = 20;

    private const CAUTION_GUST_KMH = 40;

    private const CAUTION_RAIN_MM = 5;

    private const CAUTION_RAIN_CHANCE = 60;

    /**
     * @return array{place: string, updated_at: string, current: array<string, mixed>, days: array<int, array<string, mixed>>}|null
     */
    public function forecast(?float $latitude = null, ?float $longitude = null): ?array
    {
        if (! config('map.weather.enabled', true)) {
            return null;
        }

        $here = $latitude !== null && $longitude !== null;
        $latitude = $here ? round($latitude, self::COORDINATE_PRECISION) : (float) config('map.weather.latitude');
        $longitude = $here ? round($longitude, self::COORDINATE_PRECISION) : (float) config('map.weather.longitude');
        $key = self::CACHE_KEY . ':' . ($here ? "{$latitude},{$longitude}" : 'default');

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached['forecast'] ?? null; // null = a recent failure, retried after retry_minutes
        }

        $forecast = $this->fetch($latitude, $longitude);
        if ($forecast !== null) {
            $forecast['place'] = $here ? $this->placeName($latitude, $longitude) : (string) config('map.weather.place');
            $forecast['is_current_location'] = $here;
        }

        $minutes = $forecast !== null
            ? max(1, (int) config('map.weather.cache_minutes', 60))
            : max(1, (int) config('map.weather.retry_minutes', 10));
        Cache::put($key, ['forecast' => $forecast], now()->addMinutes($minutes));

        return $forecast;
    }

    /** Barangay and municipality around the point, from the imported boundaries; else a generic label. */
    private function placeName(float $latitude, float $longitude): string
    {
        try {
            $feature = $this->boundaries->resolveFeatures([['type' => 'point', 'coords' => [$latitude, $longitude]]])[0] ?? [];
            $details = $feature['location_details'] ?? null;
            if (is_array($details)) {
                $parts = array_filter([
                    $details['barangay']['name'] ?? null,
                    $details['municipality']['name'] ?? null,
                ]);
                if ($parts !== []) {
                    return implode(', ', array_unique($parts));
                }
            }
        } catch (Throwable $exception) {
            Log::info('Field weather place lookup failed.', ['error' => $exception->getMessage()]);
        }

        return 'Your location';
    }

    private function fetch(float $latitude, float $longitude): ?array
    {
        try {
            $response = Http::timeout(max(1, (int) config('map.weather.timeout', 6)))
                ->acceptJson()
                ->get((string) config('map.weather.url'), [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'timezone' => config('map.weather.timezone', 'Asia/Manila'),
                    'forecast_days' => max(1, min(7, (int) config('map.weather.days', 4))),
                    'wind_speed_unit' => 'kmh',
                    'current' => 'temperature_2m,relative_humidity_2m,precipitation,weather_code,wind_speed_10m,wind_gusts_10m',
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,precipitation_sum,wind_speed_10m_max,wind_gusts_10m_max',
                ]);

            if (! $response->successful()) {
                Log::warning('Field weather request failed.', ['status' => $response->status()]);

                return null;
            }

            return $this->normalize((array) $response->json());
        } catch (Throwable $exception) {
            Log::warning('Field weather request failed.', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /** Turns Open-Meteo's column arrays into one entry per day, each with a field-work rating. */
    private function normalize(array $data): ?array
    {
        $daily = $data['daily'] ?? [];
        $dates = (array) ($daily['time'] ?? []);
        if ($dates === []) {
            return null;
        }

        $value = fn (string $key, int $i) => isset($daily[$key][$i]) && is_numeric($daily[$key][$i]) ? (float) $daily[$key][$i] : null;
        $today = Carbon::now((string) config('map.weather.timezone', 'Asia/Manila'))->toDateString();

        $days = [];
        foreach ($dates as $i => $date) {
            $code = (int) ($daily['weather_code'][$i] ?? 0);
            $day = [
                'date' => (string) $date,
                'label' => $this->dayLabel((string) $date, $today),
                'code' => $code,
                'description' => self::describe($code),
                'icon' => self::icon($code),
                'temp_max' => $this->round($value('temperature_2m_max', $i)),
                'temp_min' => $this->round($value('temperature_2m_min', $i)),
                'rain_chance' => $this->round($value('precipitation_probability_max', $i)),
                'rain_mm' => $this->round($value('precipitation_sum', $i), 1),
                'wind_max' => $this->round($value('wind_speed_10m_max', $i)),
                'gust_max' => $this->round($value('wind_gusts_10m_max', $i)),
            ];
            $days[] = $day + self::fieldRating($day);
        }

        $current = $data['current'] ?? [];
        $currentCode = (int) ($current['weather_code'] ?? $days[0]['code']);

        return [
            'place' => (string) config('map.weather.place'),
            'is_current_location' => false,
            'updated_at' => now()->toIso8601String(),
            'current' => [
                'temperature' => $this->round(is_numeric($current['temperature_2m'] ?? null) ? (float) $current['temperature_2m'] : null),
                'humidity' => $this->round(is_numeric($current['relative_humidity_2m'] ?? null) ? (float) $current['relative_humidity_2m'] : null),
                'wind' => $this->round(is_numeric($current['wind_speed_10m'] ?? null) ? (float) $current['wind_speed_10m'] : null),
                'gusts' => $this->round(is_numeric($current['wind_gusts_10m'] ?? null) ? (float) $current['wind_gusts_10m'] : null),
                'code' => $currentCode,
                'description' => self::describe($currentCode),
                'icon' => self::icon($currentCode),
            ],
            'days' => $days,
        ];
    }

    /**
     * good / caution / avoid for going out to the mangroves, with the reason shown on the card.
     *
     * @param  array<string, mixed>  $day
     * @return array{rating: string, rating_label: string, rating_reason: string}
     */
    public static function fieldRating(array $day): array
    {
        $code = (int) ($day['code'] ?? 0);
        $gust = (float) ($day['gust_max'] ?? 0);
        $rain = (float) ($day['rain_mm'] ?? 0);
        $chance = (float) ($day['rain_chance'] ?? 0);

        if ($code >= 95) {
            return ['rating' => 'avoid', 'rating_label' => 'Avoid field work', 'rating_reason' => 'Thunderstorms expected'];
        }
        if ($gust >= self::AVOID_GUST_KMH) {
            return ['rating' => 'avoid', 'rating_label' => 'Avoid field work', 'rating_reason' => 'Strong wind gusts up to ' . round($gust) . ' km/h'];
        }
        if ($rain >= self::AVOID_RAIN_MM) {
            return ['rating' => 'avoid', 'rating_label' => 'Avoid field work', 'rating_reason' => 'Heavy rain (' . round($rain) . ' mm)'];
        }
        if ($gust >= self::CAUTION_GUST_KMH) {
            return ['rating' => 'caution', 'rating_label' => 'Use caution', 'rating_reason' => 'Gusty wind up to ' . round($gust) . ' km/h'];
        }
        if ($rain >= self::CAUTION_RAIN_MM || $chance >= self::CAUTION_RAIN_CHANCE) {
            return ['rating' => 'caution', 'rating_label' => 'Use caution', 'rating_reason' => 'Rain likely' . ($chance > 0 ? ' (' . round($chance) . '% chance)' : '')];
        }

        return ['rating' => 'good', 'rating_label' => 'Good for field work', 'rating_reason' => 'Little rain and calm wind'];
    }

    /** WMO weather interpretation codes, as used by Open-Meteo. */
    public static function describe(int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear sky',
            $code === 1 => 'Mainly clear',
            $code === 2 => 'Partly cloudy',
            $code === 3 => 'Overcast',
            in_array($code, [45, 48], true) => 'Fog',
            in_array($code, [51, 53, 55], true) => 'Drizzle',
            in_array($code, [56, 57], true) => 'Freezing drizzle',
            in_array($code, [61, 63], true) => $code === 61 ? 'Light rain' : 'Rain',
            $code === 65 => 'Heavy rain',
            in_array($code, [66, 67], true) => 'Freezing rain',
            in_array($code, [71, 73, 75, 77], true) => 'Snow',
            in_array($code, [80, 81], true) => 'Rain showers',
            $code === 82 => 'Violent rain showers',
            in_array($code, [85, 86], true) => 'Snow showers',
            $code === 95 => 'Thunderstorm',
            in_array($code, [96, 99], true) => 'Thunderstorm with hail',
            default => 'Unknown',
        };
    }

    /** Bootstrap Icons name for a WMO code. */
    public static function icon(int $code): string
    {
        return match (true) {
            $code === 0 => 'bi-sun',
            in_array($code, [1, 2], true) => 'bi-cloud-sun',
            $code === 3 => 'bi-clouds',
            in_array($code, [45, 48], true) => 'bi-cloud-fog2',
            $code >= 51 && $code <= 57 => 'bi-cloud-drizzle',
            in_array($code, [65, 82], true) => 'bi-cloud-rain-heavy',
            ($code >= 61 && $code <= 67) || ($code >= 80 && $code <= 81) => 'bi-cloud-rain',
            ($code >= 71 && $code <= 77) || in_array($code, [85, 86], true) => 'bi-cloud-snow',
            $code >= 95 => 'bi-cloud-lightning-rain',
            default => 'bi-cloud',
        };
    }

    private function dayLabel(string $date, string $today): string
    {
        if ($date === $today) {
            return 'Today';
        }

        try {
            if ($date === Carbon::parse($today)->addDay()->toDateString()) {
                return 'Tomorrow';
            }

            return Carbon::parse($date)->format('D');
        } catch (Throwable) {
            return $date;
        }
    }

    private function round(?float $value, int $precision = 0): float|int|null
    {
        if ($value === null) {
            return null;
        }

        return $precision === 0 ? (int) round($value) : round($value, $precision);
    }
}
