<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDroneImagery;
use App\Jobs\SegmentDroneImagery;
use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\UserActivity;
use App\Notifications\DelineationModerated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/** Admin "Manage Map Layers": the mangrove delineation map and every user's drone imagery. */
class MapLayerController extends Controller
{
    private const MODULE = 'Map Layers';

    /** Status filter options per tab: value => label. */
    public const DELINEATION_STATUSES = [
        'published' => 'Published',
        'hidden' => 'Hidden from map',
        'pending' => 'Pending review',
        'rejected' => 'Rejected',
    ];

    public const IMAGERY_STATUSES = [
        'ready' => 'Ready',
        'awaiting_review' => 'Awaiting owner review',
        'finalizing' => 'Finalizing',
        'processing' => 'Processing',
        'queued' => 'Queued',
        'failed' => 'Failed',
    ];

    public function index(Request $request)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $tab = $request->query('tab') === 'imagery' ? 'imagery' : 'delineations';
        // Each tab has its own statuses (the same ones its Status dropdown offers).
        $statuses = $tab === 'imagery' ? self::IMAGERY_STATUSES : self::DELINEATION_STATUSES;
        $filters = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:' . implode(',', array_keys($statuses))],
        ])->valid();

        $data = [
            'tab' => $tab,
            'filters' => $filters,
            'statusOptions' => $statuses,
            'counts' => [
                'published' => Delineation::approved()->count(),
                'hidden' => Delineation::whereNotNull('hidden_at')->count(),
                'pending' => Delineation::pending()->count(),
                'imagery' => DroneImageryLayer::count(),
                'imageryReady' => DroneImageryLayer::where('status', 'ready')->count(),
                'imageryFailed' => DroneImageryLayer::where('status', 'failed')
                    ->orWhere('segmentation_status', 'failed')
                    ->count(),
            ],
        ];

        if ($tab === 'imagery') {
            $data['layers'] = $this->imageryQuery($filters)
                ->with('user:id,name,role')
                ->withCount('files')
                ->latest()
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();
        } else {
            $data['delineations'] = $this->delineationQuery($filters)
                ->with(['user:id,name,role', 'approvedBy:id,name', 'hiddenBy:id,name'])
                ->latest()
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();
        }

        return view('admin.map-layers', $data);
    }

    public function updateDelineation(Request $request, Delineation $delineation): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $validated['name'] = trim($validated['name']);
        $validated['notes'] = isset($validated['notes']) ? trim($validated['notes']) : null;

        if ($validated['name'] === $delineation->name && ($validated['notes'] ?: null) === ($delineation->notes ?: null)) {
            return $this->backToDelineations('No changes to save.');
        }

        DB::transaction(function () use ($delineation, $validated) {
            $delineation->recordRevision(Auth::user(), 'admin_details_edit');
            $delineation->update([
                'name' => $validated['name'],
                'notes' => $validated['notes'] ?: null,
            ]);
        });

        UserActivity::record(Auth::user(), 'Updated delineation details: ' . $delineation->name, self::MODULE, 'success', $delineation);

        return $this->backToDelineations('Delineation "' . $delineation->name . '" updated.');
    }

    public function hideDelineation(Request $request, Delineation $delineation): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        if (! $delineation->is_approved || $delineation->isHiddenFromMap()) {
            return $this->backToDelineations('Only published delineations can be hidden.', 'error');
        }

        $reason = trim((string) ($validated['reason'] ?? '')) ?: null;
        $delineation->forceFill([
            'hidden_at' => now(),
            'hidden_by' => Auth::id(),
            'hidden_reason' => $reason,
        ])->save();

        UserActivity::record(Auth::user(), 'Hid delineation from map: ' . $delineation->name, self::MODULE, 'pending', $delineation);
        $this->notifyOwner($delineation, 'hidden', $reason);

        return $this->backToDelineations('"' . $delineation->name . '" is now hidden from the map.');
    }

    public function restoreDelineation(Delineation $delineation): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        if (! $delineation->isHiddenFromMap()) {
            return $this->backToDelineations('This delineation is not hidden.', 'error');
        }

        $delineation->forceFill(['hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null])->save();

        UserActivity::record(Auth::user(), 'Restored delineation to map: ' . $delineation->name, self::MODULE, 'success', $delineation);
        $this->notifyOwner($delineation, 'restored');

        return $this->backToDelineations('"' . $delineation->name . '" is back on the map.');
    }

    public function destroyDelineation(Delineation $delineation): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $name = $delineation->name;
        $owner = $delineation->user;

        // Logged before deleting so the entry can still name the record; the subject link then shows "deleted".
        UserActivity::record(Auth::user(), 'Deleted delineation: ' . $name, self::MODULE, 'rejected', $delineation);
        $delineation->delete();

        if ($owner && (int) $owner->id !== (int) Auth::id()) {
            $owner->notify(new DelineationModerated('deleted', $name));
        }

        return $this->backToDelineations('Delineation "' . $name . '" deleted.');
    }

    public function updateImagery(Request $request, DroneImageryLayer $layer): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $validated = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $name = trim($validated['name']);

        if ($name !== $layer->name) {
            $previous = $layer->name;
            $layer->update(['name' => $name]);
            UserActivity::record(Auth::user(), "Renamed drone imagery \"{$previous}\" to \"{$name}\"", self::MODULE, 'success', $layer);
        }

        return $this->backToImagery('Imagery layer renamed.');
    }

    public function reprocessImagery(DroneImageryLayer $layer): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        if ($layer->status !== 'failed') {
            return $this->backToImagery('Only layers whose processing failed can be reprocessed.', 'error');
        }

        $layer->update(['status' => 'queued', 'error_message' => null]);

        if (! $this->dispatchSafely(fn() => ProcessDroneImagery::dispatch($layer->id), $layer, [
            'status' => 'failed',
            'error_message' => 'Unable to queue raster processing. Please try again later.',
        ])) {
            return $this->backToImagery('Processing could not be queued. Check the queue connection.', 'error');
        }

        UserActivity::record(Auth::user(), 'Re-queued drone imagery processing: ' . $layer->name, self::MODULE, 'pending', $layer);

        return $this->backToImagery('"' . $layer->name . '" was queued for processing again.');
    }

    public function resegmentImagery(DroneImageryLayer $layer): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        if ($layer->status !== 'ready' || $layer->isSegmenting()) {
            return $this->backToImagery('AI segmentation can only be run on a ready layer that is not already being segmented.', 'error');
        }

        $layer->update(['segmentation_status' => 'queued', 'segmentation_error' => null]);

        if (! $this->dispatchSafely(fn() => SegmentDroneImagery::dispatch($layer->id), $layer, [
            'segmentation_status' => 'failed',
            'segmentation_error' => 'Unable to queue AI segmentation. Please try again later.',
        ])) {
            return $this->backToImagery('AI segmentation could not be queued. Check the queue connection.', 'error');
        }

        UserActivity::record(Auth::user(), 'Queued AI segmentation: ' . $layer->name, self::MODULE, 'pending', $layer);

        return $this->backToImagery('AI segmentation queued for "' . $layer->name . '".');
    }

    public function destroyImagery(DroneImageryLayer $layer): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        if ($layer->isProcessing() || $layer->isSegmenting()) {
            return $this->backToImagery('Wait until processing and AI segmentation finish before deleting this layer.', 'error');
        }

        $name = $layer->name;
        UserActivity::record(Auth::user(), 'Deleted drone imagery: ' . $name, self::MODULE, 'rejected', $layer);
        $layer->deleteWithStoredFiles();

        return $this->backToImagery('Imagery layer "' . $name . '" and its files were deleted.');
    }

    private function delineationQuery(array $filters): Builder
    {
        return Delineation::query()
            ->select([
                'id', 'user_id', 'name', 'notes', 'features', 'is_approved', 'approved_at', 'approved_by',
                'is_rejected', 'rejected_at', 'hidden_at', 'hidden_by', 'hidden_reason', 'created_at', 'updated_at',
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $query->where(fn(Builder $query) => $query
                    ->where('name', 'like', $like)
                    ->orWhereHas('user', fn(Builder $user) => $user->where('name', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn(Builder $query, string $status) => match ($status) {
                'published' => $query->approved(),
                'hidden' => $query->whereNotNull('hidden_at'),
                'pending' => $query->pending(),
                'rejected' => $query->rejected(),
                default => $query,
            });
    }

    private function imageryQuery(array $filters): Builder
    {
        return DroneImageryLayer::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $query->where(fn(Builder $query) => $query
                    ->where('name', 'like', $like)
                    ->orWhereHas('user', fn(Builder $user) => $user->where('name', 'like', $like)));
            })
            ->when(
                array_key_exists($filters['status'] ?? '', self::IMAGERY_STATUSES),
                fn(Builder $query) => $query->where('status', $filters['status'])
            );
    }

    private function notifyOwner(Delineation $delineation, string $action, ?string $reason = null): void
    {
        $owner = $delineation->user;
        if ($owner && (int) $owner->id !== (int) Auth::id()) {
            $owner->notify(new DelineationModerated($action, $delineation->name, $delineation->id, $reason));
        }
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
