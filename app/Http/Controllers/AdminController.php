<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AIModel;
use App\Models\MangroveData;
use App\Models\Analysis;
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
        $totalDatasets = MangroveData::count();
        $activeModels = AIModel::where('is_active', true)->count();
        $recentAnalyses = Analysis::latest()->take(5)->get();

        return view('admin.dashboard', [
            'totalUsers' => $totalUsers,
            'totalDatasets' => $totalDatasets,
            'activeModels' => $activeModels,
            'recentAnalyses' => $recentAnalyses
        ]);
    }

    public function datasets()
    {
        abort_if(Auth::user()->role !== 'admin', 403);
        $datasets = MangroveData::paginate(20);
        return view('admin.dataset-management', ['datasets' => $datasets]);
    }

    public function models()
    {
        abort_if(Auth::user()->role !== 'admin', 403);
        $models = AIModel::all();
        return view('admin.model-training', ['models' => $models]);
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

        return view('admin.users', [
            'users' => $users,
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'newSignups' => $newSignups,
            'adminUsers' => $adminUsers,
            'roleCounts' => $roleCounts,
        ]);
    }

    public function show(Request $request, User $user)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        if ($request->ajax()) {
            return view('admin.partials.user-show-modal', ['user' => $user]);
        }

        return view('admin.user-show', ['user' => $user]);
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
