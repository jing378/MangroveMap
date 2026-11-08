<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\User;
use App\Models\AIModel;
use App\Models\MangroveData;
use App\Models\Analysis;
use App\Models\Delineation;
use App\Models\UserActivity;
use App\Notifications\ExpertApplicationReviewed;
use App\Services\MangroveSegmentationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AdminController extends Controller
{
    public function dashboard()
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $totalUsers = User::count();
        $mappedZones = MangroveData::count();
        $totalAnalyses = Analysis::count();
        $completedAnalyses = Analysis::where('status', 'completed')->count();
        $generaClassified = $completedAnalyses;
        $systemHealth = $totalAnalyses > 0
            ? round(($completedAnalyses / $totalAnalyses) * 100, 1)
            : 100;
        $newUsersThisMonth = User::where('created_at', '>=', now()->subMonth())->count();
        $newZonesThisMonth = MangroveData::where('created_at', '>=', now()->subMonth())->count();
        $recentActivities = UserActivity::with('user')
            ->latest()
            ->take(5)
            ->get();

        $growthChart = $this->weeklyGrowthChartData();

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
            'totalUsers' => $totalUsers,
            'mappedZones' => $mappedZones,
            'generaClassified' => $generaClassified,
            'systemHealth' => $systemHealth,
            'newUsersThisMonth' => $newUsersThisMonth,
            'newZonesThisMonth' => $newZonesThisMonth,
            'recentActivities' => $recentActivities,
            'growthChartLabels' => $growthChart['labels'],
            'growthChartZones' => $growthChart['zones'],
            'growthChartAnalyses' => $growthChart['analyses'],
            'totalContributors' => $totalContributors,
            'approvedContributors' => $approvedContributors,
            'activeContributorsThisMonth' => $activeContributorsThisMonth,
            'topContributors' => $topContributors,
        ]);
    }

    public function datasets()
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $datasets = MangroveData::with('genus')
            ->latest('observation_date')
            ->paginate(20);

        $totalDatasets = MangroveData::count();
        $totalCoverage = MangroveData::sum('coverage_area_km2') ?? 0;
        $avgConfidence = MangroveData::avg('confidence_score');
        $healthyCount = MangroveData::where('health_status', 'healthy')->count();
        $healthRate = $totalDatasets > 0
            ? round(($healthyCount / $totalDatasets) * 100, 1)
            : 0;
        $newThisMonth = MangroveData::where('created_at', '>=', now()->subMonth())->count();

        $importHistory = Analysis::with('user')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dataset-management', [
            'datasets' => $datasets,
            'totalDatasets' => $totalDatasets,
            'totalCoverage' => $totalCoverage,
            'avgConfidence' => $avgConfidence,
            'healthRate' => $healthRate,
            'newThisMonth' => $newThisMonth,
            'importHistory' => $importHistory,
        ]);
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

        $activeModel = $models->first(fn(AIModel $model) => $model->model_type === 'segmentation'
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
        $modelsWithAccuracy = $availableModels->filter(fn(AIModel $model) => $model->accuracy !== null)->count();
        $runtimeModelIsRegistered = $runtimeModelPath !== null
            && $availableModels->contains(fn(AIModel $model) => $model->artifact_path === $runtimeModelPath);
        $availableModelCount = $availableModels->count() + ($runtimeModelPath !== null && ! $runtimeModelIsRegistered ? 1 : 0);

        $accuracyChart = $completedModels
            ->filter(fn(AIModel $model) => $model->accuracy !== null)
            ->sortBy('created_at')
            ->values()
            ->map(fn(AIModel $model) => $model);

        // Genus suggestions run on their own model (YOLO .pt): an active registry model, else the configured file.
        $genusModelPath = app(MangroveSegmentationService::class)->genusWeightsPath();

        return view('admin.model-training', [
            'models' => $models,
            'availableModelCount' => $availableModelCount,
            'modelsWithAccuracy' => $modelsWithAccuracy,
            'inferenceReady' => $runtimeModelPath !== null,
            'genusReady' => $genusModelPath !== null,
            'runtimeModelSource' => $runtimeModelSource,
            'accuracyChartLabels' => $accuracyChart->map(fn(AIModel $model) => $model->name)->all(),
            'accuracyChartValues' => $accuracyChart->map(fn(AIModel $model) => $model->accuracyPercent())->all(),
            // Per bar: which model type it is (bars are colored by type) and what the figure measures.
            'accuracyChartTypes' => $accuracyChart->map(fn(AIModel $model) => $model->model_type)->all(),
            'accuracyChartMetrics' => $accuracyChart->map(fn(AIModel $model) => $model->accuracy_metric ?: 'Accuracy')->all(),
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
            'model_file' => 'required|file|extensions:' . $extension . '|max:524288',
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

        $relativePath = $checkpoint->storeAs('models', Str::uuid() . '.' . $extension, 'local');
        if (! is_string($relativePath)) {
            return back()->withErrors(['model_file' => 'The checkpoint could not be stored.'])->withInput();
        }

        $performance = AIModel::performanceAttributes($validated);
        $description = $isGenusModel
            ? 'Uploaded and validated YOLO genus model' . ($classes !== [] ? ' (' . implode(', ', $classes) . ')' : '') . '.'
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
        $validator = \Illuminate\Support\Facades\Validator::make(
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

        UserActivity::record(Auth::user(), 'Updated AI model performance: ' . $model->name, 'AI Models', 'success', $model);

        return redirect()->route('admin.models')->with('success', 'Performance figures for ' . $model->name . ' saved.');
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
            return redirect()->route('admin.models')->with('error', 'The model file could not be read: ' . $exception->getMessage());
        }

        if ($training === null || ! isset($training['map50_mask'])) {
            return redirect()->route('admin.models')->with('error', $model->name . ' has no training results saved in its file. Enter the figures from Roboflow or your training run instead.');
        }

        $model->update(AIModel::attributesFromYoloTraining($training, $classes));

        UserActivity::record(Auth::user(), 'Read AI model performance from checkpoint: ' . $model->name, 'AI Models', 'success', $model);

        return redirect()->route('admin.models')->with('success', $model->name . ': ' . $model->accuracyPercent() . '% '
            . $model->accuracy_metric . (! empty($training['dataset']) ? ' on ' . $training['dataset'] : '') . ', read from the model file.');
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
            return redirect()->route('admin.models')->with('success', $model->name . ' is already the active model.');
        }

        DB::transaction(function () use ($model): void {
            AIModel::query()
                ->where('model_type', $model->model_type)
                ->where('is_active', true)
                ->update(['is_active' => false]);
            $model->forceFill(['is_active' => true])->save();
        });

        UserActivity::record(Auth::user(), 'Activated AI model: ' . $model->name, 'AI Models', 'success', $model);

        return redirect()->route('admin.models')->with('success', $model->name . ' is now the active '
            . ($model->model_type === 'classification' ? 'genus suggestion' : 'segmentation') . ' model.');
    }

    private function weeklyGrowthChartData(): array
    {
        $labels = [];
        $zones = [];
        $analyses = [];

        for ($i = 3; $i >= 0; $i--) {
            $start = now()->subWeeks($i + 1)->startOfDay();
            $end = now()->subWeeks($i)->endOfDay();
            $labels[] = 'Week ' . (4 - $i);
            $zones[] = MangroveData::whereBetween('created_at', [$start, $end])->count();
            $analyses[] = Analysis::whereBetween('created_at', [$start, $end])->count();
        }

        return compact('labels', 'zones', 'analyses');
    }

    public function users()
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $users = User::paginate(20);
        $totalUsers = User::count();
        $activeUsers = User::whereNotNull('email_verified_at')->count();
        $newSignups = User::where('created_at', '>=', now()->subMonth())->count();
        $adminUsers = User::where('role', 'admin')->count();
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
            'activeUsers' => $activeUsers,
            'newSignups' => $newSignups,
            'adminUsers' => $adminUsers,
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

        if (!$request->filled('password') && !$request->filled('password_confirmation')) {
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
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role'                  => ['required', 'in:admin,end_user,expert'],
            'organization'          => ['nullable', 'string', 'max:255'],
            'phone'                 => ['nullable', 'regex:/^09[0-9]{9}$/', 'string'],
            'password'              => array_merge(['nullable'], $passwordRules),
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
            'email'        => $validated['email'],
            'role'         => $validated['role'],
            'organization' => $validated['organization'] ?? null,
            'phone'        => $validated['phone'] ?? null,
        ]);

        if (!empty($validated['password'])) {
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
            'Updated user profile: ' . $user->name,
            'User Management',
            'success',
            $user,
        );

        if ($request->ajax()) {
            // The Users page reloads after a modal save and shows this as its success toast.
            $request->session()->flash('success', 'User updated successfully.');

            return response()->json([
                'success' => 'User updated successfully.',
                'user'    => $user,
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

        UserActivity::record(Auth::user(), 'Approved expert application: ' . $user->name, 'User Management', 'success', $user);
        $user->deleteExpertApplicationNotifications();
        $user->notify(new ExpertApplicationReviewed(true));

        return redirect()->route('admin.users')->with('success', $user->name . ' is now an expert.');
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

        UserActivity::record(Auth::user(), 'Rejected expert application: ' . $user->name, 'User Management', 'rejected', $user);
        $user->deleteExpertApplicationNotifications();
        $user->notify(new ExpertApplicationReviewed(false, $notes));

        return redirect()->route('admin.users')->with('success', 'Expert application from ' . $user->name . ' was rejected.');
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
