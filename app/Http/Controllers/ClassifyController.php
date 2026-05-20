<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClassifyController extends Controller
{
    public function create()
    {
        return view('classify.upload');
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240'
        ]);

        // Store image
        $path = $request->file('image')->store('classifications', 'public');

        // Create analysis record
        $analysis = Analysis::create([
            'user_id' => Auth::id(),
            'image_url' => Storage::url($path),
            'analysis_type' => 'classification',
            'status' => 'processing'
        ]);

        UserActivity::recordAnalysis($analysis);

        // TODO: Call AI model for classification
        // $results = $this->callAIModel($path);
        // $analysis->update(['results' => $results, 'status' => 'completed']);

        return redirect()->route('classify.results', $analysis->id);
    }

    public function results(Analysis $analysis)
    {
        return view('classify.results', ['analysis' => $analysis]);
    }
}