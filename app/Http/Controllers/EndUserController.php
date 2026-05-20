<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Delineation;
use App\Models\MangroveData;
use App\Models\User;
use App\Models\UserActivity;
use App\Notifications\DelineationSubmittedForReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EndUserController extends Controller
{
    public function dashboard()
    {
        return view('users.end-user', $this->buildDashboardData());
    }

    public function buildDashboardData(): array
    {
        $user = Auth::user();

        $userAnalyses = Analysis::where('user_id', $user->id)
            ->latest()
            ->get();

        $totalAnalyses = $userAnalyses->count();
        $completedAnalyses = $userAnalyses->where('status', 'completed')->count();
        $pendingAnalyses = $userAnalyses->where('status', 'pending')->count();
        $failedAnalyses = $userAnalyses->where('status', 'failed')->count();
        $recentAnalyses = $userAnalyses->take(5);

        $totalCoverage = MangroveData::sum('coverage_area_km2') ?? 0;
        $genusCount = MangroveData::distinct('genus_id')->count();
        $degradedArea = MangroveData::where('health_status', 'degraded')->sum('coverage_area_km2') ?? 0;

        $genusDistribution = MangroveData::select('genus_id', DB::raw('COUNT(*) as count'))
            ->whereNotNull('genus_id')
            ->groupBy('genus_id')
            ->with('genus')
            ->limit(10)
            ->get();

        $genusLabels = [];
        $genusSeries = [];
        foreach ($genusDistribution as $item) {
            if ($item->genus) {
                $genusLabels[] = $item->genus->common_name;
                $genusSeries[] = $item->count;
            }
        }

        $coverageTrends = MangroveData::select(
            DB::raw('YEAR(observation_date) as year'),
            DB::raw('SUM(coverage_area_km2) as total')
        )
            ->groupBy(DB::raw('YEAR(observation_date)'))
            ->orderBy('year')
            ->get();

        $trendYears = $coverageTrends->pluck('year')->toArray();
        $trendValues = $coverageTrends->pluck('total')->toArray();

        $delineations = $user->delineations()->latest()->get();

        $approvedDelineations = Delineation::approved()
            ->where('user_id', '!=', $user->id)
            ->latest('approved_at')
            ->get();

        $approvedDelineationsForMap = $approvedDelineations->map(function ($d) {
            return array_merge($d->toArray(), [
                'is_system' => true,
                'approved' => true,
                'is_approved' => true,
            ]);
        })->values();

        return [
            'user' => $user,
            'totalAnalyses' => $totalAnalyses,
            'completedAnalyses' => $completedAnalyses,
            'pendingAnalyses' => $pendingAnalyses,
            'failedAnalyses' => $failedAnalyses,
            'recentAnalyses' => $recentAnalyses,
            'totalCoverage' => $totalCoverage,
            'genusCount' => $genusCount,
            'degradedArea' => $degradedArea,
            'genusLabels' => $genusLabels,
            'genusSeries' => $genusSeries,
            'trendYears' => $trendYears,
            'trendValues' => $trendValues,
            'delineations' => $delineations,
            'approvedDelineations' => $approvedDelineations,
            'approvedDelineationsForMap' => $approvedDelineationsForMap,
        ];
    }

    public function storeDelineation(Request $request)
    {
        $data = $request->validate([
            'features' => ['required', 'array'],
            'name' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
        ]);

        $delineation = Delineation::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'] ?? 'Draft delineation',
            'notes' => $data['notes'] ?? null,
            'features' => $data['features'],
        ]);

        UserActivity::recordDelineationSubmitted($delineation);

        User::where('role', 'expert')
            ->get()
            ->each(fn($expert) => $expert->notify(new DelineationSubmittedForReview($delineation)));

        return response()->json([
            'message' => 'Delineation saved as draft and submitted for expert review.',
            'delineation' => $delineation,
        ]);
    }
}
