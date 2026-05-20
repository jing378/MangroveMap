<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AIModel;
use App\Models\MangroveData;
use App\Models\Analysis;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

        $models = AIModel::orderByDesc('training_date')->get();
        $activeModels = $models->where('is_active', true)->count();
        $completedModels = $models->where('status', 'completed');
        $avgAccuracy = $completedModels->isNotEmpty()
            ? round($completedModels->avg('accuracy') * 100, 1)
            : 0;
        $trainingModels = $models->where('status', 'training')->count();
        $queuedModels = $models->whereNotIn('status', ['completed', 'training', 'failed'])->count();

        $accuracyChart = $completedModels
            ->sortBy('training_date')
            ->values()
            ->map(fn ($m) => round(($m->accuracy ?? 0) * 100, 1));

        return view('admin.model-training', [
            'models' => $models,
            'activeModels' => $activeModels,
            'avgAccuracy' => $avgAccuracy,
            'trainingModels' => $trainingModels,
            'queuedModels' => $queuedModels,
            'accuracyChartLabels' => $accuracyChart->keys()->map(fn ($i) => 'Model ' . ($i + 1))->values()->all(),
            'accuracyChartValues' => $accuracyChart->values()->all(),
            'completedModels' => $completedModels->sortByDesc('training_date')->take(5),
        ]);
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

        return view('admin.users', [
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
