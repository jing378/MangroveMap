<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\AdminReminder;
use App\Models\AIModel;
use App\Models\Delineation;
use App\Models\DroneImageryFile;
use App\Models\DroneImageryLayer;
use App\Models\Feedback;
use App\Models\TileSource;
use App\Models\User;
use App\Models\UserActivity;
use App\Notifications\ExpertApplicationReviewed;
use App\Services\ApprovedCoverageStats;
use App\Services\MangroveComparison;
use App\Services\MangroveSegmentationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AdminController extends Controller
{
    /** The dashboard's "Today" counts follow the local day, not UTC. */
    private const LOCAL_TIMEZONE = 'Asia/Manila';

    /** A pending delineation older than this many days is flagged on the dashboard. */
    private const STALE_REVIEW_DAYS = 3;

    /** aiModelStatus() for this request. */
    private ?array $aiModelStatus = null;

    private const TODAY_USERS_LIMIT = 25;

    public function dashboard(ApprovedCoverageStats $coverage, MangroveComparison $comparison)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $totalUsers = User::count();
        $newUsersThisMonth = User::where('created_at', '>=', now()->subMonth())->count();
        $expertCount = User::where('role', 'expert')->count();

        // Area mapped: expert-approved delineations, measured like the public pages.
        $coverageSummary = $coverage->summary();

        // Expert review queue.
        $pendingDelineations = Delineation::pending()->count();
        $oldestPending = Delineation::pending()->min('created_at');
        $oldestPendingAt = $oldestPending ? Carbon::parse($oldestPending) : null;

        // Drone imagery pipeline.
        $droneTotal = DroneImageryLayer::count();
        $droneProcessing = DroneImageryLayer::whereIn('status', ['queued', 'processing', 'finalizing'])->count();
        $droneFailed = DroneImageryLayer::where(fn ($query) => $query
            ->where('status', 'failed')
            ->orWhere('segmentation_status', 'failed')
            ->orWhere('classification_status', 'failed'))->count();
        $droneAwaitingOwner = DroneImageryLayer::inStage('waiting_owner')->count();

        // Disk used by uploads (as uploaded; generated tiles and mosaics come on top).
        $storage = [
            'droneBytes' => (int) DroneImageryFile::sum('file_size'),
            'tileSources' => TileSource::count(),
            'tileBytes' => (int) TileSource::sum('file_size'),
        ];

        $recentActivities = UserActivity::with('user')
            ->latest()
            ->take(5)
            ->get();

        $growthChart = $this->weeklyGrowthChartData();

        // "Today" is the local calendar day (the app stores UTC).
        $todayStart = now(self::LOCAL_TIMEZONE)->startOfDay()->utc();
        $newUsersToday = User::where('created_at', '>=', $todayStart)->count();
        $loggedInToday = User::where('last_login_at', '>=', $todayStart)->count();
        $todayUsers = User::query()
            ->where(fn ($query) => $query
                ->where('created_at', '>=', $todayStart)
                ->orWhere('last_login_at', '>=', $todayStart))
            ->orderByRaw('CASE WHEN created_at >= ? THEN 0 ELSE 1 END', [$todayStart])
            ->orderByDesc('last_login_at')
            ->orderByDesc('created_at')
            ->take(self::TODAY_USERS_LIMIT)
            ->get(['id', 'name', 'email', 'role', 'created_at', 'last_login_at', 'email_verified_at'])
            ->each(fn (User $user) => $user->setAttribute('signed_up_today', $user->created_at->gte($todayStart)));

        $totalContributors = Delineation::distinct()->count('user_id');
        $approvedContributors = Delineation::approved()->distinct()->count('user_id');
        $activeContributorsThisMonth = Delineation::where('created_at', '>=', now()->subMonth())
            ->distinct()
            ->count('user_id');
        $topContributors = Delineation::query()
            ->selectRaw('user_id, COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved_count')
            ->selectRaw('SUM(CASE WHEN is_approved = 0 AND is_rejected = 0 THEN 1 ELSE 0 END) as pending_count')
            ->selectRaw('MAX(created_at) as last_submitted_at')
            ->with('user:id,name,role')
            ->groupBy('user_id')
            ->orderByDesc('approved_count')
            ->orderByDesc('total_count')
            ->take(5)
            ->get()
            ->each->mergeCasts(['last_submitted_at' => 'datetime']);

        return view('admin.dashboard', [
            // "Compared with official figures": PSA/provincial totals, satellite per municipality, AI accuracy.
            'comparison' => $comparison->summary(),
            'totalUsers' => $totalUsers,
            'newUsersThisMonth' => $newUsersThisMonth,
            'expertCount' => $expertCount,
            'approvedHectares' => $coverageSummary['area_ha'],
            'approvedAreas' => $coverageSummary['area_count'],
            'pendingDelineations' => $pendingDelineations,
            'oldestPendingAt' => $oldestPendingAt,
            'droneTotal' => $droneTotal,
            'droneProcessing' => $droneProcessing,
            'droneFailed' => $droneFailed,
            'droneAwaitingOwner' => $droneAwaitingOwner,
            'attention' => $this->attentionItems($pendingDelineations, $oldestPendingAt, $droneFailed, DroneImageryLayer::staleOwnerDraft()->count()),
            'aiModels' => $this->aiModelStatus(),
            'storage' => $storage,
            'recentActivities' => $recentActivities,
            'growthChart' => $growthChart,
            'newUsersToday' => $newUsersToday,
            'loggedInToday' => $loggedInToday,
            'todayUsers' => $todayUsers,
            'todayTimezone' => self::LOCAL_TIMEZONE,
            'totalContributors' => $totalContributors,
            'approvedContributors' => $approvedContributors,
            'activeContributorsThisMonth' => $activeContributorsThisMonth,
            'topContributors' => $topContributors,
            'droneStages' => $this->droneStageCounts(),
            'reviewStatus' => $this->reviewStatus(),
            'hectaresByMunicipality' => $this->hectaresByMunicipality($coverageSummary['by_municipality']),
        ]);
    }

    /** How many municipalities the "Hectares by municipality" chart lists before folding the rest into "Other". */
    private const CHART_MUNICIPALITIES = 8;

    /** Reviews in this many recent days make up the "average time to review". */
    private const REVIEW_TIME_DAYS = 90;

    /**
     * Drone surveys per stage, in pipeline order (DroneImageryLayer::STAGES), for the dashboard chart.
     *
     * @return array<int, array{key: string, label: string, count: int, url: string}>
     */
    private function droneStageCounts(): array
    {
        $counts = DroneImageryLayer::query()
            ->with(['user:id,role', 'delineation:id,drone_imagery_layer_id,is_approved,is_rejected'])
            ->get(['id', 'user_id', 'status', 'segmentation_status'])
            ->countBy(fn (DroneImageryLayer $layer) => $layer->stage());

        return collect(DroneImageryLayer::STAGES)
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'count' => (int) ($counts[$key] ?? 0),
                'url' => route('admin.map-layers.index', ['tab' => 'imagery', 'status' => $key]),
            ])
            ->values()
            ->all();
    }

    /**
     * Delineations by review state, and how long recent reviews took. An expert's own delineation is approved
     * as it is saved, so it is left out of the average (it would pull it toward zero).
     *
     * @return array{segments: array<int, array{key: string, label: string, count: int, url: string}>, total: int, average_hours: ?float, reviewed: int, sources: array<string, int>}
     */
    private function reviewStatus(): array
    {
        $approved = Delineation::approved()->count();
        $pending = Delineation::pending()->count();
        $rejected = Delineation::rejected()->where('is_approved', false)->count();

        $since = now()->subDays(self::REVIEW_TIME_DAYS);
        $hours = Delineation::query()
            ->where(fn ($query) => $query->where('approved_at', '>=', $since)->orWhere('rejected_at', '>=', $since))
            ->get(['id', 'user_id', 'created_at', 'approved_at', 'approved_by', 'rejected_at', 'is_approved'])
            ->reject(fn (Delineation $d) => $d->is_approved && (int) $d->approved_by === (int) $d->user_id)
            ->map(fn (Delineation $d) => ($d->is_approved ? $d->approved_at : $d->rejected_at)?->diffInMinutes($d->created_at, true))
            ->filter(fn ($minutes) => $minutes !== null)
            ->map(fn ($minutes) => $minutes / 60);

        $segments = [
            ['key' => 'published', 'label' => 'Approved', 'count' => $approved],
            ['key' => 'pending', 'label' => 'Pending review', 'count' => $pending],
            ['key' => 'rejected', 'label' => 'Rejected', 'count' => $rejected],
        ];

        return [
            'segments' => array_map(fn (array $segment) => $segment + [
                'url' => route('admin.map-layers.index', ['status' => $segment['key']]),
            ], $segments),
            'total' => $approved + $pending + $rejected,
            'average_hours' => $hours->isEmpty() ? null : round($hours->avg(), 1),
            'reviewed' => $hours->count(),
            'review_days' => self::REVIEW_TIME_DAYS,
            // Where every delineation came from (Delineation::SOURCES), for the line under the bar.
            'sources' => collect(Delineation::SOURCES)
                ->map(fn (string $label, string $key) => Delineation::where('source', $key)->count())
                ->all(),
        ];
    }

    /**
     * Approved hectares per municipality, largest first; past CHART_MUNICIPALITIES the rest are one "Other"
     * row, and areas not matched to a municipality yet come last.
     *
     * @param  array<int, array{name: ?string, area_count: int, area_ha: float}>  $byMunicipality  ApprovedCoverageStats summary
     * @return array<int, array{name: string, area_ha: float, area_count: int, url: ?string, muted: bool}>
     */
    private function hectaresByMunicipality(array $byMunicipality): array
    {
        $named = collect($byMunicipality)->filter(fn (array $row) => $row['name'] !== null)->values();
        $unmatched = collect($byMunicipality)->first(fn (array $row) => $row['name'] === null);

        $rows = $named->take(self::CHART_MUNICIPALITIES)
            ->map(fn (array $row) => [
                'name' => $row['name'],
                'area_ha' => (float) $row['area_ha'],
                'area_count' => (int) $row['area_count'],
                // Map Layers' search also matches the place names kept in the features.
                'url' => route('admin.map-layers.index', ['status' => 'published', 'search' => $row['name']]),
                'muted' => false,
            ]);

        $rest = $named->slice(self::CHART_MUNICIPALITIES);
        if ($rest->isNotEmpty()) {
            $rows->push([
                'name' => 'Other ('.$rest->count().')',
                'area_ha' => round((float) $rest->sum('area_ha'), 2),
                'area_count' => (int) $rest->sum('area_count'),
                'url' => null,
                'muted' => true,
            ]);
        }
        if ($unmatched) {
            $rows->push([
                'name' => 'Not matched yet',
                'area_ha' => (float) $unmatched['area_ha'],
                'area_count' => (int) $unmatched['area_count'],
                'url' => null,
                'muted' => true,
            ]);
        }

        return $rows->values()->all();
    }

    public function models()
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $models = AIModel::orderByDesc('created_at')->get()->map(function (AIModel $model) {
            $artifactPath = $this->resolveModelArtifactPath($model->model_path);
            $model->setAttribute('artifact_path', $artifactPath);
            $model->setAttribute('artifact_available', $artifactPath !== null);

            return $model;
        });

        $activeModel = $models->first(fn (AIModel $model) => $model->model_type === 'segmentation'
            && $model->is_active
            && $model->artifact_available
            && in_array(strtolower(pathinfo($model->artifact_path, PATHINFO_EXTENSION)), AIModel::runnableExtensions('segmentation'), true));

        $runtimeModelPath = $activeModel?->artifact_path;
        $runtimeModelSource = 'Active registry model';
        if ($runtimeModelPath === null && is_file((string) config('ml.model_path'))) {
            $runtimeModelPath = config('ml.model_path');
            $runtimeModelSource = 'Configured default checkpoint';
        }

        $availableModels = $models->where('artifact_available', true);
        $completedModels = $availableModels->where('status', 'completed');
        $modelsWithAccuracy = $availableModels->filter(fn (AIModel $model) => $model->accuracy !== null)->count();
        $runtimeModelIsRegistered = $runtimeModelPath !== null
            && $availableModels->contains(fn (AIModel $model) => $model->artifact_path === $runtimeModelPath);
        $availableModelCount = $availableModels->count() + ($runtimeModelPath !== null && ! $runtimeModelIsRegistered ? 1 : 0);

        $accuracyChart = $completedModels
            ->filter(fn (AIModel $model) => $model->accuracy !== null)
            ->sortBy('created_at')
            ->values()
            ->map(fn (AIModel $model) => $model);

        // Genus suggestions run on their own model (YOLO .pt): an active registry model, else the configured file.
        $genusModelPath = app(MangroveSegmentationService::class)->genusWeightsPath();
        // The hosted Roboflow Workflow has no file here, so it is not in the registry; show it beside it.
        $usesRoboflow = app(MangroveSegmentationService::class)->extentModel() === 'roboflow';
        $genusOnRoboflow = (bool) config('ml.genus.enabled', true)
            && app(MangroveSegmentationService::class)->genusModel() === 'roboflow';

        return view('admin.model-training', [
            'models' => $models,
            'availableModelCount' => $availableModelCount,
            'modelsWithAccuracy' => $modelsWithAccuracy,
            'inferenceReady' => $runtimeModelPath !== null || $usesRoboflow,
            // Both hosted Roboflow Workflows, listed whenever an API key is set, in use or not.
            'hostedModels' => trim((string) config('ml.roboflow.api_key')) === '' ? [] : [
                [
                    'kind' => 'extent',
                    'in_use' => $usesRoboflow,
                    'label' => $usesRoboflow ? 'Mangrove areas are drawn by' : 'Can draw the mangrove areas',
                    'workflow' => (string) config('ml.roboflow.workflow'),
                    'workspace' => (string) config('ml.roboflow.workspace'),
                    'local' => $runtimeModelPath !== null ? basename((string) $runtimeModelPath) : null,
                    'local_kind' => 'U-Net checkpoint',
                    'setting' => 'ML_DRONE_EXTENT_MODEL=roboflow',
                ],
                [
                    'kind' => 'genus',
                    'in_use' => $genusOnRoboflow,
                    'label' => $genusOnRoboflow ? 'Canopies and genus are found by' : 'Can find the canopies and genus',
                    'workflow' => (string) config('ml.roboflow.genus_workflow'),
                    'workspace' => (string) config('ml.roboflow.workspace'),
                    'local' => $genusModelPath !== null ? basename((string) $genusModelPath) : null,
                    'local_kind' => 'YOLO genus model',
                    'setting' => 'ML_GENUS_MODEL=roboflow',
                ],
            ],
            'genusReady' => $genusModelPath !== null || $genusOnRoboflow,
            'runtimeModelSource' => $runtimeModelSource,
            'accuracyChartLabels' => $accuracyChart->map(fn (AIModel $model) => $model->name)->all(),
            'accuracyChartValues' => $accuracyChart->map(fn (AIModel $model) => $model->accuracyPercent())->all(),
            // Per bar: which model type it is (bars are colored by type) and what the figure measures.
            'accuracyChartTypes' => $accuracyChart->map(fn (AIModel $model) => $model->model_type)->all(),
            'accuracyChartMetrics' => $accuracyChart->map(fn (AIModel $model) => $model->accuracy_metric ?: 'Accuracy')->all(),
            'completedModels' => $completedModels->sortByDesc('created_at')->take(5),
        ]);
    }

    private function resolveModelArtifactPath(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (is_file($path)) {
            return $path;
        }

        $privatePath = Storage::disk('local')->path($path);

        return is_file($privatePath) ? $privatePath : null;
    }

    public function uploadModel(Request $request, MangroveSegmentationService $segmentation): RedirectResponse
    {
        $isGenusModel = $request->input('model_type') === 'classification';
        $extension = $isGenusModel ? 'pt' : 'pth';
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:ai_models,name',
            'version' => 'required|string|max:50',
            'model_type' => 'required|in:segmentation,classification',
            'model_file' => 'required|file|extensions:'.$extension.'|max:524288',
        ] + AIModel::performanceRules(), AIModel::performanceMessages());

        $checkpoint = $request->file('model_file');
        $classes = [];
        $training = null;

        try {
            if ($isGenusModel) {
                ['classes' => $classes, 'training' => $training] = $segmentation->inspectGenusWeights($checkpoint->getPathname());
            } else {
                $segmentation->validateWeights($checkpoint->getPathname());
            }
        } catch (RuntimeException) {
            return back()->withErrors([
                'model_file' => $isGenusModel
                    ? 'This checkpoint is invalid or incompatible. Upload an Ultralytics YOLO segmentation model (.pt) whose classes are mangrove genera.'
                    : 'This checkpoint is invalid or incompatible. Upload a PyTorch U-Net with a ResNet34 encoder, 3 input channels, and 2 output classes.',
            ])->withInput();
        }

        $relativePath = $checkpoint->storeAs('models', Str::uuid().'.'.$extension, 'local');
        if (! is_string($relativePath)) {
            return back()->withErrors(['model_file' => 'The checkpoint could not be stored.'])->withInput();
        }

        $performance = AIModel::performanceAttributes($validated);
        $description = $isGenusModel
            ? 'Uploaded and validated YOLO genus model'.($classes !== [] ? ' ('.implode(', ', $classes).')' : '').'.'
            : 'Uploaded and validated PyTorch U-Net checkpoint.';
        $trainingDate = null;

        // A YOLO checkpoint carries its own validation results (e.g. from Colab training on a Roboflow
        // export): use them, unless the admin typed their own accuracy.
        if ($isGenusModel && $training !== null) {
            $fromCheckpoint = AIModel::attributesFromYoloTraining($training, $classes);
            $description = $fromCheckpoint['description'];
            $trainingDate = $fromCheckpoint['training_date'] ?? null;
            if ($performance['accuracy'] === null && isset($fromCheckpoint['accuracy'])) {
                $performance['accuracy'] = $fromCheckpoint['accuracy'];
                $performance['accuracy_metric'] = $fromCheckpoint['accuracy_metric'];
            }
        }

        try {
            DB::transaction(function () use ($validated, $relativePath, $description, $performance, $trainingDate): void {
                AIModel::query()
                    ->where('model_type', $validated['model_type'])
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                AIModel::create($performance + [
                    'name' => $validated['name'],
                    'model_type' => $validated['model_type'],
                    'version' => $validated['version'],
                    'training_date' => $trainingDate,
                    'status' => 'completed',
                    'description' => $description,
                    'model_path' => Storage::disk('local')->path($relativePath),
                    'is_active' => true,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($relativePath);
            throw $exception;
        }

        return redirect()->route('admin.models')->with('success', $isGenusModel
            ? 'Model uploaded, validated, and activated for genus suggestions.'
            : 'Model uploaded, validated, and activated for segmentation.');
    }

    /** Add or correct a model's performance figures from training (accuracy, metric, dataset size). */
    public function updateModelMetrics(Request $request, AIModel $model): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        // Errors go to their own bag so the page can reopen this model's edit dialog with them.
        $validator = Validator::make(
            $request->all(),
            AIModel::performanceRules(),
            AIModel::performanceMessages(),
        );
        if ($validator->fails()) {
            return redirect()->route('admin.models')
                ->withErrors($validator, 'metrics')
                ->withInput($request->only('accuracy', 'accuracy_metric', 'dataset_size') + ['metrics_model_id' => $model->id]);
        }

        $model->update(AIModel::performanceAttributes($validator->validated()));

        UserActivity::record(Auth::user(), 'Updated AI model performance: '.$model->name, 'AI Models', 'success', $model);

        return redirect()->route('admin.models')->with('success', 'Performance figures for '.$model->name.' saved.');
    }

    /** Fill a YOLO genus model's figures from the validation results saved inside its checkpoint. */
    public function readModelMetricsFromCheckpoint(AIModel $model, MangroveSegmentationService $segmentation): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $path = $this->resolveModelArtifactPath($model->model_path);
        if ($model->model_type !== 'classification' || $path === null || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pt') {
            return redirect()->route('admin.models')->with('error', 'Only YOLO genus models (.pt) store their training results in the file.');
        }

        try {
            ['classes' => $classes, 'training' => $training] = $segmentation->inspectGenusWeights($path);
        } catch (RuntimeException $exception) {
            return redirect()->route('admin.models')->with('error', 'The model file could not be read: '.$exception->getMessage());
        }

        if ($training === null || ! isset($training['map50_mask'])) {
            return redirect()->route('admin.models')->with('error', $model->name.' has no training results saved in its file. Enter the figures from Roboflow or your training run instead.');
        }

        $model->update(AIModel::attributesFromYoloTraining($training, $classes));

        UserActivity::record(Auth::user(), 'Read AI model performance from checkpoint: '.$model->name, 'AI Models', 'success', $model);

        return redirect()->route('admin.models')->with('success', $model->name.': '.$model->accuracyPercent().'% '
            .$model->accuracy_metric.(! empty($training['dataset']) ? ' on '.$training['dataset'] : '').', read from the model file.');
    }

    /** Make an earlier uploaded model the active one for its type again (no re-upload needed). */
    public function activateModel(AIModel $model): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $path = $this->resolveModelArtifactPath($model->model_path);

        if ($path === null || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), AIModel::runnableExtensions($model->model_type), true)) {
            return redirect()->route('admin.models')->with('error', 'This model cannot be activated: its file is missing or is not a supported checkpoint.');
        }

        if ($model->is_active) {
            return redirect()->route('admin.models')->with('success', $model->name.' is already the active model.');
        }

        DB::transaction(function () use ($model): void {
            AIModel::query()
                ->where('model_type', $model->model_type)
                ->where('is_active', true)
                ->update(['is_active' => false]);
            $model->forceFill(['is_active' => true])->save();
        });

        UserActivity::record(Auth::user(), 'Activated AI model: '.$model->name, 'AI Models', 'success', $model);

        return redirect()->route('admin.models')->with('success', $model->name.' is now the active '
            .($model->model_type === 'classification' ? 'genus suggestion' : 'segmentation').' model.');
    }

    /**
     * Stop using a model for new AI runs without deleting it. With no active model of its type, the pipeline
     * uses the default file in ml/models (MangroveSegmentationService); it can be activated again any time.
     */
    public function deactivateModel(AIModel $model): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        if (! $model->is_active) {
            return redirect()->route('admin.models')->with('success', $model->name.' is not active.');
        }

        $model->forceFill(['is_active' => false])->save();
        UserActivity::record(Auth::user(), 'Deactivated AI model: '.$model->name, 'AI Models', 'success', $model);

        $kind = $model->model_type === 'classification' ? 'genus' : 'segmentation';

        return redirect()->route('admin.models')->with('success', $model->name.' is no longer active. New AI runs use the default '
            .$kind.' model file until you activate a model.');
    }

    /**
     * Remove a model from the registry and its uploaded checkpoint. Only files in the upload folder
     * (storage models/) are deleted; a record pointing anywhere else (e.g. the default files in ml/models)
     * loses only its registry entry. Deleting the active model leaves the pipeline on the default model
     * file until another one is activated (see aiModelStatus / MangroveSegmentationService).
     */
    public function destroyModel(AIModel $model): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $name = $model->name;
        $wasActive = (bool) $model->is_active;
        $file = $this->resolveModelArtifactPath($model->model_path);
        $uploadsDir = realpath(Storage::disk('local')->path('models'));
        $realFile = $file !== null ? realpath($file) : false;
        $isUpload = $realFile !== false && $uploadsDir !== false
            && str_starts_with(strtolower($realFile), strtolower($uploadsDir.DIRECTORY_SEPARATOR));

        // A running AI job may hold the file open (Windows refuses to delete it then): keep the record and say so.
        if ($isUpload && ! @unlink($realFile)) {
            return redirect()->route('admin.models')->with('error',
                'The file of '.$name.' could not be deleted, probably because an AI job is using it. Try again when it finishes.');
        }

        UserActivity::record(Auth::user(), 'Deleted AI model: '.$name, 'AI Models', 'success', $model);
        $model->delete();

        $kind = $model->model_type === 'classification' ? 'genus' : 'segmentation';

        return redirect()->route('admin.models')->with('success', $name.' was deleted'
            .($wasActive ? '. New AI runs use the default '.$kind.' model file until you activate another one.' : '.'));
    }

    /** New users, submitted delineations and drone uploads in each of the last 4 weeks (local time). */
    private function weeklyGrowthChartData(): array
    {
        $labels = [];
        $series = ['users' => [], 'delineations' => [], 'drone' => []];

        // The last 4 weeks as back-to-back 7-day windows ending today (local time), so no day is counted twice.
        $today = now(self::LOCAL_TIMEZONE)->startOfDay();

        for ($i = 3; $i >= 0; $i--) {
            $start = $today->copy()->subDays(7 * $i + 6);
            $end = $today->copy()->subDays(7 * $i)->addDay();
            $labels[] = $start->format('M j').' – '.$end->copy()->subDay()->format('M j');
            $from = $start->copy()->utc();
            $to = $end->copy()->utc();
            $series['users'][] = User::where('created_at', '>=', $from)->where('created_at', '<', $to)->count();
            $series['delineations'][] = Delineation::where('created_at', '>=', $from)->where('created_at', '<', $to)->count();
            $series['drone'][] = DroneImageryLayer::where('created_at', '>=', $from)->where('created_at', '<', $to)->count();
        }

        return ['labels' => $labels] + $series;
    }

    /**
     * What an admin should look at now, most urgent first.
     *
     * @return array<int, array{tone: string, icon: string, title: string, text: string, url: string, action: string}>
     */
    private function attentionItems(int $pendingDelineations, ?Carbon $oldestPendingAt, int $droneFailed, int $staleDrafts): array
    {
        $items = [];
        $plural = fn (int $count, string $word) => $count.' '.Str::plural($word, $count);

        foreach ($this->aiModelStatus() as $model) {
            if (! $model['ready']) {
                $items[] = ['tone' => 'danger', 'icon' => 'bi-cpu', 'title' => "No {$model['label']} model",
                    'text' => $model['missing_text'], 'url' => route('admin.models'), 'action' => 'AI Models'];
            }
        }

        if ($droneFailed > 0) {
            $items[] = ['tone' => 'danger', 'icon' => 'bi-exclamation-octagon', 'title' => $plural($droneFailed, 'drone layer').' failed',
                'text' => 'Processing or an AI step failed. Reprocess or remove them.',
                'url' => route('admin.map-layers.index', ['tab' => 'imagery', 'status' => 'failed']), 'action' => 'Drone imagery'];
        }

        $applications = User::pendingExpertApplications()->count();
        if ($applications > 0) {
            $items[] = ['tone' => 'warning', 'icon' => 'bi-person-badge', 'title' => $plural($applications, 'expert application').' waiting',
                'text' => 'Residents asked to become experts. Approve or reject them.',
                'url' => route('admin.users').'#expert-applications', 'action' => 'Review'];
        }

        if ($pendingDelineations > 0 && $oldestPendingAt !== null && $oldestPendingAt->lt(now()->subDays(self::STALE_REVIEW_DAYS))) {
            $items[] = ['tone' => 'warning', 'icon' => 'bi-hourglass-split', 'title' => $plural($pendingDelineations, 'delineation').' waiting for experts',
                'text' => 'The oldest was submitted '.$oldestPendingAt->diffForHumans().'. Remind the experts to review them.',
                'url' => route('admin.map-layers.index', ['status' => 'pending']), 'action' => 'Pending',
                'reminder' => [
                    'kind' => AdminReminder::PENDING_REVIEWS,
                    'title' => 'Remind the experts',
                    'who' => 'Every expert ('.User::where('role', User::ROLE_EXPERT)->count().') is reminded that '
                        .$plural($pendingDelineations, 'delineation').' '.($pendingDelineations === 1 ? 'is' : 'are').' waiting for review.',
                    'recent' => AdminReminder::recent(AdminReminder::PENDING_REVIEWS),
                ]];
        }

        if ($staleDrafts > 0) {
            $items[] = ['tone' => 'warning', 'icon' => 'bi-camera', 'title' => $plural($staleDrafts, 'AI draft').' not confirmed',
                'text' => 'Drone imagery has waited over '.DroneImageryLayer::STALE_DRAFT_DAYS.' days for its owner to confirm the mangrove areas. Remind them, or delete abandoned layers.',
                'url' => route('admin.map-layers.index', ['tab' => 'imagery', 'status' => 'waiting_owner']), 'action' => 'Waiting',
                'reminder' => [
                    'kind' => AdminReminder::STALE_DRAFTS,
                    'title' => 'Remind the owners',
                    'who' => 'Each owner of the '.$plural($staleDrafts, 'drone survey').' waiting over '.DroneImageryLayer::STALE_DRAFT_DAYS
                        .' days is reminded to confirm the mangrove areas, naming their surveys.',
                    'recent' => AdminReminder::recent(AdminReminder::STALE_DRAFTS),
                ]];
        }

        $newFeedback = Feedback::where('status', 'new')->count();
        if ($newFeedback > 0) {
            $items[] = ['tone' => 'info', 'icon' => 'bi-chat-left-text', 'title' => $plural($newFeedback, 'new feedback message'),
                'text' => 'Users sent feedback that nobody has looked at yet.',
                'url' => route('admin.feedback.index', ['status' => 'new']), 'action' => 'Feedback'];
        }

        return $items;
    }

    /**
     * The AI models the pipeline would use right now: the newest active uploaded one, else the default file in ml/models.
     *
     * @return array<string, array{label: string, ready: bool, name: ?string, detail: string, missing_text: string}>
     */
    private function aiModelStatus(): array
    {
        if ($this->aiModelStatus !== null) {
            return $this->aiModelStatus;
        }

        $kinds = [
            'segmentation' => ['label' => 'mangrove area', 'extension' => 'pth', 'default' => (string) config('ml.model_path'), 'enabled' => true],
            'classification' => ['label' => 'genus', 'extension' => 'pt', 'default' => (string) config('ml.genus.model_path'), 'enabled' => (bool) config('ml.genus.enabled', true)],
        ];

        $status = [];
        foreach ($kinds as $type => $kind) {
            $model = AIModel::query()
                ->where('model_type', $type)
                ->where('is_active', true)
                ->whereNotNull('model_path')
                ->orderByDesc('id')
                ->get()
                ->first(fn (AIModel $model) => strtolower(pathinfo($model->model_path, PATHINFO_EXTENSION)) === $kind['extension']
                    && is_file($model->model_path));
            $hasDefault = $kind['default'] !== '' && is_file($kind['default']);

            $status[$type] = [
                'label' => $kind['label'],
                'ready' => ! $kind['enabled'] || $model !== null || $hasDefault,
                'name' => $model?->name ?? ($hasDefault ? basename($kind['default']) : null),
                'detail' => match (true) {
                    ! $kind['enabled'] => 'Turned off in settings',
                    // Show the version only when it is a real one that the name does not already contain.
                    $model !== null => preg_match('/^v?\d/i', (string) $model->version) && ! str_contains(strtolower($model->name), strtolower(ltrim((string) $model->version, 'vV')))
                        ? 'Uploaded model · version '.ltrim($model->version, 'vV')
                        : 'Uploaded model',
                    $hasDefault => 'Default file in ml/models',
                    default => 'No weights found',
                },
                'missing_text' => $type === 'segmentation'
                    ? 'The AI cannot draw mangrove areas on drone imagery or Tile Explorer uploads. Upload a .pth model.'
                    : 'Drone imagery gets no genus suggestion or canopies. Upload a .pt model.',
            ];
        }

        // The hosted Roboflow Workflow draws the mangrove areas (ML_DRONE_EXTENT_MODEL=roboflow); the U-Net
        // above is then only the fallback.
        if (app(MangroveSegmentationService::class)->extentModel() === 'roboflow') {
            $fallback = $status['segmentation']['ready'] ? ' · falls back to '.$status['segmentation']['name'] : '';
            $status['segmentation'] = [
                'ready' => true,
                'name' => 'Roboflow workflow',
                'detail' => 'Hosted on Roboflow (RF-DETR)'.$fallback,
            ] + $status['segmentation'];
        }

        // Likewise the hosted canopy Workflow finds the canopies and genus (ML_GENUS_MODEL=roboflow).
        if ((bool) config('ml.genus.enabled', true) && app(MangroveSegmentationService::class)->genusModel() === 'roboflow') {
            $fallback = $status['classification']['ready'] && $status['classification']['name'] !== null
                ? ' · falls back to '.$status['classification']['name'] : '';
            $status['classification'] = [
                'ready' => true,
                'name' => 'Roboflow workflow',
                'detail' => 'Hosted on Roboflow (RF-DETR)'.$fallback,
            ] + $status['classification'];
        }

        return $this->aiModelStatus = $status;
    }

    public function users()
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $users = User::paginate(20);
        $totalUsers = User::count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $newSignups = User::where('created_at', '>=', now()->subMonth())->count();
        $adminUsers = User::where('role', 'admin')->count();
        // Logged in right now (a live session), and the ids so each row can say so.
        $loggedInNowIds = User::loggedInNowIds();
        $loggedInNowCount = count($loggedInNowIds);
        $roleCounts = User::select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->pluck('count', 'role')
            ->toArray();

        $recentActivities = UserActivity::with('user')
            ->latest()
            ->take(10)
            ->get();

        $expertApplications = User::pendingExpertApplications()
            ->orderBy('expert_applied_at')
            ->get();

        return view('admin.users', [
            'expertApplications' => $expertApplications,
            'users' => $users,
            'totalUsers' => $totalUsers,
            'verifiedUsers' => $verifiedUsers,
            'newSignups' => $newSignups,
            'adminUsers' => $adminUsers,
            'loggedInNowIds' => $loggedInNowIds,
            'loggedInNowCount' => $loggedInNowCount,
            'roleCounts' => $roleCounts,
            'recentActivities' => $recentActivities,
        ]);
    }

    public function show(Request $request, User $user)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $userActivities = $user->activities()->latest()->take(15)->get();

        if ($request->ajax()) {
            return view('admin.partials.user-show-modal', [
                'user' => $user,
                'userActivities' => $userActivities,
            ]);
        }

        return view('admin.user-show', [
            'user' => $user,
            'userActivities' => $userActivities,
        ]);
    }

    public function edit(Request $request, User $user)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        if ($request->ajax()) {
            return view('admin.partials.user-edit-modal', ['user' => $user]);
        }

        return view('admin.user-edit', ['user' => $user]);
    }

    public function update(Request $request, User $user)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        if (! $request->filled('password') && ! $request->filled('password_confirmation')) {
            $request->request->remove('password');
            $request->request->remove('password_confirmation');
        }

        // The modal sends the name in parts (like sign-up and the profile page); older callers send one full name.
        $usesNameParts = $request->has('first_name');
        if ($usesNameParts) {
            $request->merge([
                'first_name' => trim((string) $request->input('first_name')),
                'middle_name' => trim((string) $request->input('middle_name')),
                'last_name' => trim((string) $request->input('last_name')),
                'suffix' => trim((string) $request->input('suffix')),
            ]);
        }

        $nameRules = $usesNameParts
            ? [
                'first_name' => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'suffix' => ['nullable', 'string', 'max:10', 'regex:/^[\pL.\s]+$/u'],
            ]
            : ['name' => ['required', 'string', 'max:255']];

        // Same password rules as sign-up, but optional: blank keeps the current password.
        $passwordRules = array_values(array_diff(RegisteredUserController::passwordRules(), ['required']));

        $validated = $request->validate($nameRules + [
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:admin,end_user,expert'],
            'organization' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^09[0-9]{9}$/', 'string'],
            'password' => array_merge(['nullable'], $passwordRules),
            'password_confirmation' => ['nullable', 'string'],
        ], RegisteredUserController::passwordMessages() + [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'middle_name.max' => 'Middle name must be 100 characters or fewer.',
            'suffix.max' => 'Suffix must be 10 characters or fewer (e.g. Jr., Sr., III).',
            'suffix.regex' => 'Suffix can only contain letters and periods (e.g. Jr., Sr., III).',
            'email.unique' => RegisteredUserController::EMAIL_TAKEN_MESSAGE,
            'role.required' => 'Choose a role for this user.',
            'phone.regex' => 'Phone number must be 11 digits starting with 09 (e.g. 09123456789).',
        ]);

        // Admin access must not be lost by accident: admins cannot change their own role,
        // and the last remaining admin account cannot be demoted.
        if ($user->isAdmin() && $validated['role'] !== User::ROLE_ADMIN) {
            $message = Auth::id() === $user->id
                ? 'You cannot change your own administrator role.'
                : (User::where('role', User::ROLE_ADMIN)->count() <= 1
                    ? 'At least one administrator account must remain.'
                    : null);

            if ($message !== null) {
                throw ValidationException::withMessages(['role' => $message]);
            }
        }

        // The User model rebuilds the display name from the parts (or the parts from a full name).
        $nameFields = $usesNameParts
            ? [
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?: null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?: null,
            ]
            : ['name' => $validated['name']];

        $user->fill($nameFields + [
            'email' => $validated['email'],
            'role' => $validated['role'],
            'organization' => $validated['organization'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        // Promoting an applicant by hand settles their pending application too.
        if ($validated['role'] === 'expert' && $user->hasPendingExpertApplication()) {
            $user->forceFill([
                'expert_application_status' => User::EXPERT_APPLICATION_APPROVED,
                'expert_reviewed_at' => now(),
                'expert_reviewed_by' => Auth::id(),
            ]);
            $user->deleteExpertApplicationNotifications(); // before save, while the original name is known
        }

        $user->save();

        UserActivity::record(
            Auth::user(),
            'Updated user profile: '.$user->name,
            'User Management',
            'success',
            $user,
        );

        if ($request->ajax()) {
            // The Users page reloads after a modal save and shows this as its success toast.
            $request->session()->flash('success', 'User updated successfully.');

            return response()->json([
                'success' => 'User updated successfully.',
                'user' => $user,
            ]);
        }

        return redirect()->route('admin.users.edit', $user)->with('success', 'User updated successfully.');
    }

    /** Opens the certificate an applicant uploaded with their expert application (admins only). */
    public function expertCertificate(User $user)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $path = $user->expert_certificate_path;
        $disk = Storage::disk('local');
        abort_if(! $path || ! $disk->exists($path), 404);

        $name = $user->expert_certificate_name ?: basename($path);

        // PDFs and images open in the browser tab; X-Content-Type-Options stops type sniffing.
        return $disk->response($path, $name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function approveExpertApplication(User $user): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        if (! $user->hasPendingExpertApplication()) {
            return redirect()->route('admin.users')->with('error', 'This expert application has already been reviewed.');
        }

        $user->forceFill([
            'role' => 'expert',
            'expert_application_status' => User::EXPERT_APPLICATION_APPROVED,
            'expert_reviewed_at' => now(),
            'expert_reviewed_by' => Auth::id(),
            'expert_rejection_notes' => null,
        ])->save();

        UserActivity::record(Auth::user(), 'Approved expert application: '.$user->name, 'User Management', 'success', $user);
        $user->deleteExpertApplicationNotifications();
        $user->notify(new ExpertApplicationReviewed(true));

        return redirect()->route('admin.users')->with('success', $user->name.' is now an expert.');
    }

    public function rejectExpertApplication(Request $request, User $user): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $validated = $request->validate([
            'rejection_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $user->hasPendingExpertApplication()) {
            return redirect()->route('admin.users')->with('error', 'This expert application has already been reviewed.');
        }

        $notes = trim((string) ($validated['rejection_notes'] ?? '')) ?: null;

        $user->forceFill([
            'expert_application_status' => User::EXPERT_APPLICATION_REJECTED,
            'expert_reviewed_at' => now(),
            'expert_reviewed_by' => Auth::id(),
            'expert_rejection_notes' => $notes,
        ])->save();

        UserActivity::record(Auth::user(), 'Rejected expert application: '.$user->name, 'User Management', 'rejected', $user);
        $user->deleteExpertApplicationNotifications();
        $user->notify(new ExpertApplicationReviewed(false, $notes));

        return redirect()->route('admin.users')->with('success', 'Expert application from '.$user->name.' was rejected.');
    }

    public function destroy(User $user)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        if (Auth::id() === $user->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted successfully.');
    }
}
