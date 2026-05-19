<?php

use App\Http\Controllers\Api\MangroveDataController;
use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\AIModelController;
use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('mangrove-data', MangroveDataController::class);
    Route::apiResource('analysis', AnalysisController::class);
    Route::apiResource('ai-models', AIModelController::class);
    
    // Map data endpoint
    Route::get('/map-data', [MapController::class, 'getData']);
});