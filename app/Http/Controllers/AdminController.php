<?php

namespace App\Http\Controllers;

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
            && strtolower(pathinfo($model->artifact_path, PATHINFO_EXTENSION)) === 'pth');

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

        return view('admin.model-training', [
            'models' => $models,
            'availableModelCount' => $availableModelCount,
            'modelsWithAccuracy' => $modelsWithAccuracy,
            'inferenceReady' => $runtimeModelPath !== null,
            'accuracyChartLabels' => $accuracyChart->map(fn(AIModel $model) => $model->name)->all(),
            'accuracyChartValues' => $accuracyChart->map(fn(AIModel $model) => round($model->accuracy * 100, 1))->all(),
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
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:ai_models,name',
            'version' => 'required|string|max:50',
            'model_type' => 'required|in:segmentation',
            'model_file' => 'required|file|extensions:pth|max:524288',
        ]);

        $checkpoint = $request->file('model_file');

        try {
            $segmentation->validateWeights($checkpoint->getPathname());
        } catch (RuntimeException) {
            return back()->withErrors([
                'model_file' => 'This checkpoint is invalid or incompatible. Upload a PyTorch U-Net with a ResNet34 encoder, 3 input channels, and 2 output classes.',
            ])->withInput();
        }

        $relativePath = $checkpoint->storeAs('models', Str::uuid() . '.pth', 'local');
        if (! is_string($relativePath)) {
            return back()->withErrors(['model_file' => 'The checkpoint could not be stored.'])->withInput();
        }

        try {
            DB::transaction(function () use ($validated, $relativePath): void {
                AIModel::query()
                    ->where('model_type', 'segmentation')
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                AIModel::create([
                    'name' => $validated['name'],
                    'model_type' => $validated['model_type'],
                    'version' => $validated['version'],
                    'accuracy' => null,
                    'training_date' => null,
                    'dataset_size' => null,
                    'status' => 'completed',
                    'description' => 'Uploaded and validated PyTorch U-Net checkpoint.',
                    'model_path' => Storage::disk('local')->path($relativePath),
                    'is_active' => true,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($relativePath);
            throw $exception;
        }

        return redirect()->route('admin.models')->with('status', 'Model uploaded, validated, and activated for segmentation.');
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

        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role'                  => ['required', 'in:admin,end_user,expert'],
            'organization'          => ['nullable', 'string', 'max:255'],
            'phone'                 => ['nullable', 'regex:/^[0-9]{11}$/', 'string'],
            'password'              => ['nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['nullable', 'string'],
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

        $user->fill([
            'name'         => $validated['name'],
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
            return response()->json([
                'success' => 'User updated successfully.',
                'user'    => $user,
            ]);
        }

        return redirect()->route('admin.users.edit', $user)->with('success', 'User updated successfully.');
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
