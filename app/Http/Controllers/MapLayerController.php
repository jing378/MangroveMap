<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDroneImagery;
use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\UserActivity;
use App\Notifications\DelineationModerated;
use App\Services\ApprovedCoverageStats;
use App\Services\DelineationObservationRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Admin "Map Layers": the mangrove delineation map and every user's drone imagery, with CSV export of each
 * list and GeoJSON of the delineations.
 */
class MapLayerController extends Controller
{
    private const MODULE = 'Map Layers';

    /** Status filter options per tab: value => label. */
    public const DELINEATION_STATUSES = [
        'published' => 'Published',
        'pending' => 'Pending review',
        'rejected' => 'Rejected',
    ];

    /** The imagery tab filters by stage (DroneImageryLayer::STAGES); "Failed" also covers failed AI steps. */
    public const IMAGERY_STATUSES = [
        ...DroneImageryLayer::STAGES,
        'waiting_owner' => 'Waiting for owner (AI draft)',
        'failed' => 'Failed (processing or AI)',
    ];

    /** Badge class per stage. */
    public const STAGE_BADGES = [
        'processing' => 'pending',
        'detecting' => 'pending',
        'waiting_owner' => 'pending',
        'finalizing' => 'pending',
        'pending_expert' => 'pending',
        'rejected' => 'rejected',
        'published' => 'success',
        'not_submitted' => 'secondary',
        'failed' => 'rejected',
    ];

    public const TABS = ['delineations', 'imagery'];

    public function index(Request $request)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        [$tab, $filters] = $this->tabAndFilters($request);
        $statuses = $this->statusOptions($tab);

        $data = [
            'tab' => $tab,
            'filters' => $filters,
            'statusOptions' => $statuses,
            'counts' => [
                'published' => Delineation::approved()->count(),
                'pending' => Delineation::pending()->count(),
                'waitingOwner' => DroneImageryLayer::inStage('waiting_owner')->count(),
                'staleDrafts' => DroneImageryLayer::staleOwnerDraft()->count(),
                'imageryFailed' => $this->failedImagery(DroneImageryLayer::query())->count(),
            ],
        ];

        if ($tab === 'imagery') {
            $data['layers'] = $this->imageryQuery($filters)
                // The delineation submitted from each layer, drawn on the imagery in the preview.
                ->with(['user:id,name,role', 'delineation:id,drone_imagery_layer_id,name,features,is_approved,is_rejected'])
                ->withCount('files')
                ->withSum('files', 'file_size')
                ->latest()
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();
        } else {
            $data['delineations'] = $this->delineationQuery($filters)
                ->with([
                    'user:id,name,role', 'approvedBy:id,name',
                    'droneImageryLayer:id,user_id,status,segmentation_status,segmented_at,created_at,bounds,zoom_min,zoom_max,preview_path,preview_bounds',
                ])
                ->latest()
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();
        }

        return view('admin.map-layers', $data);
    }

    public function destroyDelineation(Delineation $delineation): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $name = $delineation->name;
        $owner = $delineation->user;

        // Logged before deleting so the entry can still name the record; the subject link then shows "deleted".
        UserActivity::record(Auth::user(), 'Deleted delineation: '.$name, self::MODULE, 'success', $delineation);
        $delineation->delete();

        if ($owner && (int) $owner->id !== (int) Auth::id()) {
            $owner->notify(new DelineationModerated('deleted', $name));
        }

        return $this->backToDelineations('Delineation "'.$name.'" deleted.');
    }

    public function reprocessImagery(DroneImageryLayer $layer): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        if ($layer->status !== 'failed') {
            return $this->backToImagery('Only layers whose processing failed can be reprocessed.', 'error');
        }

        $layer->update(['status' => 'queued', 'error_message' => null]);

        if (! $this->dispatchSafely(fn () => ProcessDroneImagery::dispatch($layer->id), $layer, [
            'status' => 'failed',
            'error_message' => 'Unable to queue raster processing. Please try again later.',
        ])) {
            return $this->backToImagery('Processing could not be queued. Check the queue connection.', 'error');
        }

        UserActivity::record(Auth::user(), 'Re-queued drone imagery processing: '.$layer->name, self::MODULE, 'pending', $layer);

        return $this->backToImagery('"'.$layer->name.'" was queued for processing again.');
    }

    public function destroyImagery(DroneImageryLayer $layer): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        if ($layer->isProcessing() || $layer->isRunningAi()) {
            return $this->backToImagery('Wait until processing and AI detection finish before deleting this layer.', 'error');
        }

        $name = $layer->name;
        // A drone survey's delineation goes with its imagery (as when the owner deletes the layer), so no
        // delineation is left claiming a drone survey with nothing to show. The confirm dialog says so.
        $delineation = $layer->delineation;
        UserActivity::record(Auth::user(), 'Deleted drone imagery: '.$name, self::MODULE, 'success', $layer);
        if ($delineation !== null) {
            $owner = $delineation->user;
            UserActivity::record(Auth::user(), 'Deleted delineation: '.$delineation->name, self::MODULE, 'success', $delineation);
            $delineation->delete();
            if ($owner && (int) $owner->id !== (int) Auth::id()) {
                $owner->notify(new DelineationModerated('deleted', $delineation->name));
            }
        }
        $layer->deleteWithStoredFiles();

        return $this->backToImagery('Drone survey "'.$name.'" and its files were deleted'
            .($delineation !== null ? ', with its delineation "'.$delineation->name.'".' : '.'));
    }

    private function delineationQuery(array $filters): Builder
    {
        return Delineation::query()
            ->select([
                'id', 'user_id', 'source', 'name', 'notes', 'features', 'is_approved', 'approved_at', 'approved_by',
                'is_rejected', 'rejected_at', 'created_at', 'updated_at',
                'drone_imagery_layer_id',
            ])
            ->when($filters['delineation'] ?? null, fn (Builder $query, $id) => $query->whereKey((int) $id))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('features', 'like', $like) // barangay and municipality names are kept in the features
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => match ($status) {
                'published' => $query->approved(),
                'pending' => $query->pending(),
                'rejected' => $query->rejected(),
                default => $query,
            });
    }

    private function imageryQuery(array $filters): Builder
    {
        return DroneImageryLayer::query()
            ->when($filters['layer'] ?? null, fn (Builder $query, $id) => $query->whereKey((int) $id))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $like)
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $stage) => $stage === 'failed'
                ? $this->failedImagery($query)
                : $query->inStage($stage));
    }

    /** Processing failed, or the AI delineation or genus detection did. */
    private function failedImagery(Builder $query): Builder
    {
        return $query->where(fn (Builder $failed) => $failed
            ->where('status', 'failed')
            ->orWhere('segmentation_status', 'failed')
            ->orWhere('classification_status', 'failed'));
    }

    /** @return array{0: string, 1: array} the tab and its valid filters (bad values are dropped). */
    private function tabAndFilters(Request $request): array
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'delineations';
        $rules = [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:'.implode(',', array_keys($this->statusOptions($tab)))],
            // Hand-drawn or from a drone survey (delineations only).
            'source' => $tab === 'delineations' ? ['nullable', 'in:'.implode(',', array_keys(Delineation::SOURCES))] : ['prohibited'],
            // One record, from the other tab's link (a delineation's drone layer, a layer's delineation).
            'delineation' => ['nullable', 'integer', 'min:1'],
            'layer' => ['nullable', 'integer', 'min:1'],
        ];
        // valid() also returns query keys without a rule (tab, page): keep only the filters, so "Clear"
        // and the exports see just what the admin chose.
        $filters = array_intersect_key(Validator::make($request->query(), $rules)->valid(), $rules);

        return [$tab, $filters];
    }

    /** Each tab has its own statuses (the same ones its Status dropdown offers). */
    private function statusOptions(string $tab): array
    {
        return $tab === 'imagery' ? self::IMAGERY_STATUSES : self::DELINEATION_STATUSES;
    }

    /**
     * Every row of the tab that matches its search and status (not only one page): CSV, or GeoJSON polygons
     * for delineations (published ones unless another status is chosen).
     */
    public function export(Request $request): StreamedResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        [$tab, $filters] = $this->tabAndFilters($request);
        $stamp = now()->format('Y-m-d');

        if ($tab === 'delineations' && $request->query('format') === 'geojson') {
            $filters['status'] ??= 'published';

            return response()->streamDownload(
                fn () => $this->writeGeoJson($filters),
                "mangrovemap-delineations-{$stamp}.geojson",
                ['Content-Type' => 'application/geo+json'],
            );
        }

        return response()->streamDownload(function () use ($tab, $filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads names as UTF-8
            $tab === 'imagery' ? $this->writeImageryCsv($out, $filters) : $this->writeDelineationsCsv($out, $filters);
            fclose($out);
        }, "mangrovemap-{$tab}-{$stamp}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Status label and badge class of a delineation, as the table shows it. */
    public static function delineationStatus(Delineation $delineation): array
    {
        return match (true) {
            $delineation->is_approved => ['Published', 'success'],
            $delineation->is_rejected => ['Rejected', 'rejected'],
            default => ['Pending review', 'pending'],
        };
    }

    /** Hectares, municipality, barangays and genera of a delineation's drawn areas. */
    public static function delineationFacts(Delineation $delineation): array
    {
        $features = array_values(array_filter($delineation->features ?? [], 'is_array'));

        return [
            'hectares' => ApprovedCoverageStats::areaHectares(array_values(array_filter(
                $features,
                fn (array $feature) => ($feature['type'] ?? 'area') === 'area',
            ))),
            'municipality' => ApprovedCoverageStats::municipalityName($features),
            'barangays' => ApprovedCoverageStats::barangayNames($features),
            'genera' => DelineationObservationRecorder::generaPresent($features),
        ];
    }

    /**
     * What the preview map can draw for a drone layer, as data-* attributes for its preview button: the map tiles
     * once it is ready, else the review image while it awaits its owner's review (no tiles exist yet), else nothing.
     * Relative URLs: absolute ones can come out as http:// behind the https tunnel and get blocked.
     *
     * @return array<string, string>
     */
    public static function imageryPreviewAttributes(?DroneImageryLayer $layer): array
    {
        if ($layer?->status === 'ready' && $layer->bounds) {
            return [
                'tile-url' => parse_url(url("/drone-imagery/{$layer->id}/tiles"), PHP_URL_PATH).'/{z}/{x}/{y}',
                'bounds' => json_encode($layer->bounds),
                'zoom-min' => (string) $layer->zoom_min,
                'zoom-max' => (string) $layer->zoom_max,
            ];
        }

        if ($layer?->status === 'awaiting_review' && $layer->preview_path && $layer->preview_bounds) {
            return [
                'preview-url' => route('drone-imagery.preview', $layer->id, false),
                'bounds' => json_encode($layer->preview_bounds),
            ];
        }

        return [];
    }

    /** The AI's most likely genus for a drone layer, e.g. "Rhizophora 72%", or null. */
    public static function topGenus(DroneImageryLayer $layer): ?string
    {
        $suggestion = $layer->classification_stats['genus_suggestion'] ?? null;
        if (! is_array($suggestion) || ($suggestion['probability_basis'] ?? 'none') === 'none') {
            return null;
        }
        $top = $suggestion['probabilities'][0] ?? null;

        return is_array($top) && isset($top['genus'])
            ? $top['genus'].' '.round(((float) ($top['probability'] ?? 0)) * 100).'%'
            : null;
    }

    public static function formatBytes(?int $bytes): string
    {
        $bytes = (int) $bytes;
        if ($bytes <= 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), $power >= 2 ? 1 : 0).' '.$units[$power];
    }

    private function writeDelineationsCsv($out, array $filters): void
    {
        fputcsv($out, ['ID', 'Name', 'Status', 'Source', 'Owner', 'Owner role', 'Municipality', 'Barangays', 'Hectares', 'Genera', 'From drone layer', 'Submitted', 'Approved', 'Approved by']);
        $this->delineationQuery($filters)
            ->with(['user:id,name,role', 'approvedBy:id,name'])
            ->latest()->orderByDesc('id')
            ->chunk(200, function ($delineations) use ($out) {
                foreach ($delineations as $delineation) {
                    $facts = self::delineationFacts($delineation);
                    fputcsv($out, [
                        $delineation->id,
                        $delineation->name,
                        self::delineationStatus($delineation)[0],
                        $delineation->sourceLabel(),
                        $delineation->user?->name,
                        $delineation->user?->roleLabel(),
                        $facts['municipality'],
                        implode('; ', $facts['barangays']),
                        round($facts['hectares'], 4),
                        implode('; ', $facts['genera']),
                        $delineation->drone_imagery_layer_id,
                        $delineation->created_at?->toDateTimeString(),
                        $delineation->approved_at?->toDateTimeString(),
                        $delineation->approvedBy?->name,
                    ]);
                }
            });
    }

    private function writeImageryCsv($out, array $filters): void
    {
        fputcsv($out, ['ID', 'Name', 'Stage', 'Owner', 'Owner role', 'Files', 'Size (bytes)', 'Source CRS', 'AI areas', 'AI genus', 'Top genus', 'Canopies', 'Uploaded', 'Processed']);
        $this->imageryQuery($filters)
            ->with(['user:id,name,role', 'delineation:id,drone_imagery_layer_id,is_approved,is_rejected'])
            ->withCount('files')
            ->withSum('files', 'file_size')
            ->latest()->orderByDesc('id')
            ->chunk(200, function ($layers) use ($out) {
                foreach ($layers as $layer) {
                    fputcsv($out, [
                        $layer->id,
                        $layer->name,
                        DroneImageryLayer::STAGES[$layer->stage()],
                        $layer->user?->name,
                        $layer->user?->roleLabel(),
                        $layer->files_count,
                        (int) $layer->files_sum_file_size,
                        $layer->source_crs,
                        $layer->segmentation_status,
                        $layer->classification_status,
                        self::topGenus($layer),
                        $layer->classification_stats['canopy_count'] ?? null,
                        $layer->created_at?->toDateTimeString(),
                        $layer->processed_at?->toDateTimeString(),
                    ]);
                }
            });
    }

    /** One GeoJSON polygon per drawn area (WGS 84, [lng, lat]) with its delineation's details. */
    private function writeGeoJson(array $filters): void
    {
        echo '{"type":"FeatureCollection","features":[';
        $first = true;
        $this->delineationQuery($filters)
            ->with('user:id,name')
            ->latest()->orderByDesc('id')
            ->chunk(200, function ($delineations) use (&$first) {
                foreach ($delineations as $delineation) {
                    $facts = self::delineationFacts($delineation);
                    foreach ($delineation->features ?? [] as $feature) {
                        if (! is_array($feature) || ($feature['type'] ?? 'area') !== 'area' || ! is_array($feature['coords'] ?? null) || count($feature['coords']) < 3) {
                            continue;
                        }
                        $ring = array_map(fn ($pair) => [(float) $pair[1], (float) $pair[0]], array_values($feature['coords']));
                        if ($ring[0] !== end($ring)) {
                            $ring[] = $ring[0];
                        }
                        echo ($first ? '' : ',').json_encode([
                            'type' => 'Feature',
                            'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
                            'properties' => [
                                'delineation_id' => $delineation->id,
                                'name' => $delineation->name,
                                'status' => self::delineationStatus($delineation)[0],
                                'source' => $delineation->source,
                                'owner' => $delineation->user?->name,
                                'municipality' => $facts['municipality'],
                                'barangays' => $facts['barangays'],
                                'genera' => is_array($feature['genera'] ?? null) ? array_values($feature['genera']) : [],
                                'delineation_hectares' => round($facts['hectares'], 4),
                                'submitted_at' => $delineation->created_at?->toIso8601String(),
                                'approved_at' => $delineation->approved_at?->toIso8601String(),
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $first = false;
                    }
                }
            });
        echo ']}';
    }

    private function dispatchSafely(callable $dispatch, DroneImageryLayer $layer, array $failureState): bool
    {
        try {
            $dispatch();

            return true;
        } catch (Throwable $exception) {
            $layer->update($failureState);
            Log::error('Unable to queue drone imagery job from Map Layers.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);

            return false;
        }
    }

    private function backToDelineations(string $message, string $type = 'success'): RedirectResponse
    {
        return $this->backToTab('delineations', $message, $type);
    }

    private function backToImagery(string $message, string $type = 'success'): RedirectResponse
    {
        return $this->backToTab('imagery', $message, $type);
    }

    /** Return to the same filtered/paged tab the admin acted from, or the tab's first page. */
    private function backToTab(string $tab, string $message, string $type): RedirectResponse
    {
        $indexUrl = route('admin.map-layers.index');
        $previous = (string) url()->previous();
        $previousIsTab = str_starts_with($previous, $indexUrl)
            && str_contains($previous, 'tab=imagery') === ($tab === 'imagery');

        $target = $previousIsTab
            ? $previous
            : ($tab === 'imagery' ? route('admin.map-layers.index', ['tab' => 'imagery']) : $indexUrl);

        return redirect()->to($target)->with($type, $message);
    }
}
