<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Tile Explorer: a GeoTIFF uploaded only to be cut into tiles. It is kept exactly as uploaded
 * (tiles keep its coordinate system) and never goes on the map or to expert review.
 *
 * Files: tile-explorer/{id}/source.tif, areas.geojson (AI mangrove areas, EPSG:4326), tiles/{size}/...
 */
class TileSource extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'original_name',
        'stored_path',
        'file_size',
        'crs',
        'width',
        'height',
        'areas_status',
        'areas_error',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tileSets(): HasMany
    {
        return $this->hasMany(TileSet::class);
    }

    public function directory(): string
    {
        return "tile-explorer/{$this->id}";
    }

    public function areasPath(): string
    {
        return $this->directory().'/areas.geojson';
    }

    /** Deletes the uploaded file and every tile made from it, then the rows. */
    public function deleteWithFiles(): void
    {
        if (! Storage::disk('local')->deleteDirectory($this->directory())) {
            throw new RuntimeException('Unable to remove the stored GeoTIFF and its tiles.');
        }

        $this->delete();
    }
}
