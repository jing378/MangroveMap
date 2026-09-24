<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Analysis extends Model
{
    protected $table = 'analysis';

    protected $fillable = [
        'user_id',
        'mangrove_data_id',
        'analysis_type',
        'image_url',
        'species_detected',
        'classification_confidence',
        'detected_damage',
        'recommendations',
        'status',
        'results'
    ];

    protected $casts = [
        'classification_confidence' => 'float',
        'results' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mangroveData(): BelongsTo
    {
        return $this->belongsTo(MangroveData::class);
    }

    public function deleteStoredFiles(): void
    {
        $urls = array_filter([
            $this->image_url,
            $this->results['overlay_url'] ?? null,
        ]);

        foreach ($urls as $url) {
            $relative = $this->storagePathFromUrl((string) $url);
            if ($relative && Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            }
        }
    }

    private function storagePathFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $marker = '/storage/';
        $idx = strpos($path, $marker);
        if ($idx !== false) {
            return ltrim(substr($path, $idx + strlen($marker)), '/');
        }

        return ltrim($path, '/') ?: null;
    }
}
