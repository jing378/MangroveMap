<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\UserActivity;
use App\Notifications\AnalysisCompleted;
use App\Services\MangroveSegmentationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DelineationController extends Controller
{
    public function index()
    {
        $analyses = Analysis::where('user_id', Auth::id())
            ->where('analysis_type', 'classification')
            ->latest()
            ->take(50)
            ->get();

        return view('delineation.delineation', compact('analyses'));
    }

    public function store(Request $request, MangroveSegmentationService $segmentation)
    {
        set_time_limit((int) config('ml.timeout', 180) + 20);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        $path = $request->file('image')->store('classifications', 'public');
        $overlayRelative = 'classifications/' . pathinfo($path, PATHINFO_FILENAME) . '_overlay.png';

        $imageUrl = '/storage/' . $path;
        $overlayUrl = '/storage/' . $overlayRelative;

        $analysis = Analysis::create([
            'user_id'       => Auth::id(),
            'image_url'     => $imageUrl,
            'analysis_type' => 'classification',
            'status'        => 'processing',
        ]);

        try {
            $prediction = $segmentation->predict(
                Storage::disk('public')->path($path),
                Storage::disk('public')->path($overlayRelative),
            );

            $coverage   = (float) ($prediction['mangrove_coverage_percent'] ?? 0);
            $confidence = (float) ($prediction['mean_confidence'] ?? 0);
            $label      = (string) ($prediction['label'] ?? 'Mangrove');

            $analysis->update([
                'species_detected'          => $label,
                'classification_confidence' => round(min($confidence, 0.9999), 4),
                'status'                    => 'completed',
                'recommendations'           => $this->recommendation($coverage),
                'results'                   => [
                    'type'                      => 'segmentation',
                    'overlay_url'               => $overlayUrl,
                    'mangrove_coverage_percent' => $coverage,
                    'mean_confidence'           => $confidence,
                    'pixels_mangrove'           => $prediction['pixels_mangrove'] ?? null,
                    'pixels_total'              => $prediction['pixels_total'] ?? null,
                    'classes'                   => $prediction['classes'] ?? ['background', 'mangrove'],
                    'device'                    => $prediction['device'] ?? null,
                ],
            ]);

            UserActivity::recordAnalysis($analysis->fresh());

            try {
                $request->user()->notify(new AnalysisCompleted('Mangrove segmentation', (string) $analysis->id));
            } catch (\Throwable $e) {
                report($e);
            }
        } catch (RuntimeException $e) {
            $analysis->update([
                'status'  => 'failed',
                'results' => ['error' => $e->getMessage()],
            ]);
            UserActivity::recordAnalysis($analysis->fresh());

            return response()->json([
                'ok'          => false,
                'message'     => $e->getMessage(),
                'analysis_id' => $analysis->id,
            ], 500);
        }

        $analysis->refresh();

        return response()->json([
            'ok'                        => true,
            'analysis_id'               => $analysis->id,
            'image_url'                 => $analysis->image_url ?: $imageUrl,
            'overlay_url'               => $overlayUrl,
            'label'                     => $analysis->species_detected,
            'mangrove_coverage_percent' => $analysis->results['mangrove_coverage_percent'] ?? 0,
            'mean_confidence'           => $analysis->classification_confidence,
            'recommendations'           => $analysis->recommendations,
        ]);
    }

    private function recommendation(float $coverage): string
    {
        if ($coverage < 1) {
            return 'Little or no mangrove cover was detected. Try a clearer field or drone photo of the stand, or confirm this site is non-mangrove.';
        }
        if ($coverage < 20) {
            return 'Sparse mangrove cover detected. Consider field validation and whether adjacent mudflat is suitable for planting.';
        }
        return 'Mangrove canopy detected. Use the overlay to review extent, then continue routine monitoring.';
    }
}
