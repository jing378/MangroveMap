<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AIModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AIModelController extends Controller
{
    public function index()
    {
        // Only admins can view AI models
        abort_if(Auth::user()->role !== 'admin', 403);
        
        return AIModel::all();
    }

    public function show(AIModel $aiModel)
    {
        abort_if(Auth::user()->role !== 'admin', 403);
        
        return $aiModel;
    }

    public function store(Request $request)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $validated = $request->validate([
            'name' => 'required|string|unique:ai_models',
            'model_type' => 'required|in:segmentation,classification,change_detection',
            'version' => 'required|string',
            'accuracy' => 'required|numeric|min:0|max:100',
            'dataset_size' => 'required|integer'
        ]);

        return AIModel::create(array_merge($validated, [
            'status' => 'training',
            'training_date' => now()
        ]));
    }

    public function update(Request $request, AIModel $aiModel)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $validated = $request->validate([
            'name' => 'sometimes|string|unique:ai_models,name,' . $aiModel->id,
            'model_type' => 'sometimes|in:segmentation,classification,change_detection',
            'version' => 'sometimes|string',
            'accuracy' => 'sometimes|numeric|min:0|max:100',
            'dataset_size' => 'sometimes|integer'
        ]);

        $aiModel->update($validated);
        return $aiModel;
    }

    public function destroy(AIModel $aiModel)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $aiModel->delete();
        return response()->json(null, 204);
    }
}