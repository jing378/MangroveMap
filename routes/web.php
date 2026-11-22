<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DelineationAnnualObservationController;
use App\Http\Controllers\DroneImageryController;
use App\Http\Controllers\DroneImageryUploadController;
use App\Http\Controllers\EndUserController;
use App\Http\Controllers\ExpertController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\MapLayerController;
use App\Http\Controllers\MapMangroveHistoryController;
use App\Http\Controllers\MapPlaceLabelController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlantingRecommendationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TileExplorerController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('/register', function () {
        // Debug: Check authentication status
        if (Auth::check()) {
            Log::info('User tried to access register while authenticated', [
                'user_id' => Auth::id(),
                'user_email' => Auth::user()->email,
                'email_verified' => Auth::user()->email_verified_at ? 'yes' : 'no',
            ]);

            return redirect('/')->with('info', 'You are already logged in.');
        }

        return view('auth.register');
    })->name('register');

    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');

    // Live "email already taken" check for the sign-up form (rate limited).
    Route::get('/register/check-email', [RegisteredUserController::class, 'checkEmail'])
        ->middleware('throttle:30,1')
        ->name('register.check-email');

    Route::get('/forgot-password', function () {
        return view('auth.forgot-password');
    })->name('password.request');

    // Login, sign-up and password forms are rate limited per IP (AppServiceProvider::configureAuthRateLimits).
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-email')
        ->name('password.email');

    Route::get('/reset-password/{token}', function ($token) {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request('email'),
        ]);
    })->name('password.reset');

    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

// Email Verification Routes

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', function (Request $request) {
        $user = Auth::user();

        // Verified meanwhile (the link was opened on another device while this page stayed open):
        // sign in again with the credentials instead of going straight to the dashboard.
        if ($user->hasVerifiedEmail()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Email verified successfully! Please log in to continue.');
        }

        return view('auth.verify-email');
    })->name('verification.notice');

    Route::post('/email/verification-notification', function (Request $request) {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect($user->homeRoute());
        }

        $user->sendEmailVerificationNotification();

        return back()->with('message', 'Verification link sent!');
    })->middleware('throttle:6,1')->name('verification.send');
});

// The link in the verification email. It works without being logged in (it is often opened in another
// browser or on a phone): the signed URL and the email hash prove who it is for. After verifying, the
// user is always sent to the login page to sign in with their credentials, never straight to the dashboard.
Route::get('/email/verify/{id}/{hash}', function (Request $request, string $id, string $hash) {
    $user = User::find($id);

    if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
        abort(403);
    }

    $alreadyVerified = $user->hasVerifiedEmail();

    if (! $alreadyVerified && $user->markEmailAsVerified()) {
        event(new Verified($user));
    }

    // Someone already signed in with a verified account just reopened an old link.
    if ($alreadyVerified && Auth::id() === $user->id) {
        return redirect($user->homeRoute());
    }

    if (Auth::check()) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    return redirect()->route('login')->with('status', $alreadyVerified
        ? 'Your email is already verified. Please log in.'
        : 'Email verified successfully! Please log in to continue.');
})->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

// Public routes
Route::get('/', function () {
    if (Auth::check()) {
        return redirect(Auth::user()->homeRoute());
    }

    return app(PublicController::class)->index();
})->name('index');

Route::view('/terms', 'terms')->name('terms');

// Landing page map: tiles of published (expert-approved) drone imagery only.
Route::get('/imagery/{layerId}/tiles/{z}/{x}/{y}', [DroneImageryController::class, 'publicTile'])
    ->whereNumber(['layerId', 'z', 'x', 'y'])
    ->name('public.drone-imagery.tile');

// OpenStreetMap place names drawn on the satellite basemap (every map, including the landing page).
Route::get('/map/place-labels', MapPlaceLabelController::class)
    ->middleware('throttle:60,1')
    ->name('map.place-labels');

// Where mangroves stood in each year since 1985 (Global Mangrove Watch, public data) for the map's year slider.
Route::get('/map/mangrove-history', MapMangroveHistoryController::class)
    ->middleware('throttle:60,1')
    ->name('map.mangrove-history');

Route::middleware(['auth', 'verified'])->group(function () {
    // Drone Upload tab: upload GeoTIFFs, then review and manage them
    Route::get('/drone-upload', [DroneImageryController::class, 'page'])->name('drone-imagery.page');
    Route::get('/drone-imagery', [DroneImageryController::class, 'index'])
        ->name('drone-imagery.index');
    Route::post('/drone-imagery', [DroneImageryController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('drone-imagery.store');
    // Chunked, resumable upload (what the Drone Upload page uses): start, send slices in parallel, complete.
    Route::post('/drone-imagery/uploads', [DroneImageryUploadController::class, 'start'])
        ->middleware('throttle:20,1')
        ->name('drone-imagery.uploads.start');
    Route::get('/drone-imagery/uploads/{uploadId}', [DroneImageryUploadController::class, 'status'])
        ->whereUuid('uploadId')
        ->name('drone-imagery.uploads.status');
    Route::put('/drone-imagery/uploads/{uploadId}/files/{fileIndex}/chunks/{chunkIndex}', [DroneImageryUploadController::class, 'chunk'])
        ->whereUuid('uploadId')
        ->whereNumber(['fileIndex', 'chunkIndex'])
        ->middleware('throttle:3000,1')
        ->name('drone-imagery.uploads.chunk');
    Route::post('/drone-imagery/uploads/{uploadId}/complete', [DroneImageryUploadController::class, 'complete'])
        ->whereUuid('uploadId')
        ->middleware('throttle:20,1')
        ->name('drone-imagery.uploads.complete');
    Route::delete('/drone-imagery/uploads/{uploadId}', [DroneImageryUploadController::class, 'cancel'])
        ->whereUuid('uploadId')
        ->name('drone-imagery.uploads.cancel');

    // Tile Explorer: upload a GeoTIFF (chunked uploads above, purpose "tiles"), cut it into tiles, download a selection
    Route::get('/tile-explorer', [TileExplorerController::class, 'page'])->name('tile-explorer.page');
    Route::post('/tile-explorer/sources/{sourceId}/sets', [TileExplorerController::class, 'storeSet'])
        ->whereNumber('sourceId')
        ->middleware('throttle:20,1')
        ->name('tile-explorer.sets.store');
    Route::delete('/tile-explorer/sources/{sourceId}', [TileExplorerController::class, 'destroySource'])
        ->whereNumber('sourceId')
        ->name('tile-explorer.sources.destroy');
    Route::get('/tile-explorer/sets/{tileSetId}', [TileExplorerController::class, 'show'])
        ->whereNumber('tileSetId')
        ->name('tile-explorer.sets.show');
    Route::get('/tile-explorer/sets/{tileSetId}/tiles/{tileId}/{variant}', [TileExplorerController::class, 'image'])
        ->whereNumber('tileSetId')
        ->where('variant', 'thumb|full')
        ->name('tile-explorer.tiles.image');
    Route::post('/tile-explorer/sets/{tileSetId}/download', [TileExplorerController::class, 'download'])
        ->whereNumber('tileSetId')
        ->middleware('throttle:20,1')
        ->name('tile-explorer.sets.download');
    Route::post('/tile-explorer/sets/{tileSetId}/drone-imagery', [TileExplorerController::class, 'sendToDroneImagery'])
        ->whereNumber('tileSetId')
        ->middleware('throttle:10,1,tile-explorer.sets.drone-imagery')
        ->name('tile-explorer.sets.drone-imagery');
    Route::delete('/drone-imagery/{layerId}', [DroneImageryController::class, 'destroy'])
        ->whereNumber('layerId')
        ->name('drone-imagery.destroy');
    Route::get('/drone-imagery/{layerId}/tiles/{z}/{x}/{y}', [DroneImageryController::class, 'tile'])
        ->whereNumber(['layerId', 'z', 'x', 'y'])
        ->name('drone-imagery.tile');
    Route::get('/drone-imagery/{layerId}/canopies', [DroneImageryController::class, 'canopies'])
        ->whereNumber('layerId')
        ->name('drone-imagery.canopies.show');
    // Review editor: Save draft / Confirm sends the canopies the owner removed.
    Route::put('/drone-imagery/{layerId}/canopies', [DroneImageryController::class, 'updateCanopies'])
        ->whereNumber('layerId')
        ->middleware('throttle:60,1')
        ->name('drone-imagery.canopies.update');
    Route::get('/drone-imagery/{layerId}/segmentation', [DroneImageryController::class, 'segmentation'])
        ->whereNumber('layerId')
        ->name('drone-imagery.segmentation.show');
    Route::post('/drone-imagery/{layerId}/segmentation', [DroneImageryController::class, 'segment'])
        ->whereNumber('layerId')
        ->middleware('throttle:10,1')
        ->name('drone-imagery.segmentation.store');
    // Re-run only the genus classification (ClassifyDroneImagery); the mangrove areas stay.
    Route::post('/drone-imagery/{layerId}/classification', [DroneImageryController::class, 'classify'])
        ->whereNumber('layerId')
        ->middleware('throttle:10,1')
        ->name('drone-imagery.classification.store');
    // Review editor: preview the prepared mosaic, mask non-mangrove areas, adjust, then confirm onto the map
    Route::get('/drone-imagery/{layerId}/edit', [DroneImageryController::class, 'edit'])
        ->whereNumber('layerId')
        ->name('drone-imagery.edit');
    Route::get('/drone-imagery/{layerId}/preview', [DroneImageryController::class, 'preview'])
        ->whereNumber('layerId')
        ->name('drone-imagery.preview');
    Route::put('/drone-imagery/{layerId}/edits', [DroneImageryController::class, 'updateEdits'])
        ->whereNumber('layerId')
        ->middleware('throttle:60,1')
        ->name('drone-imagery.edits.update');
    Route::put('/drone-imagery/{layerId}/segmentation', [DroneImageryController::class, 'updateSegmentation'])
        ->whereNumber('layerId')
        ->middleware('throttle:60,1')
        ->name('drone-imagery.segmentation.update');
    // Live genus suggestion for unsaved area edits (recount only, nothing is saved).
    Route::post('/drone-imagery/{layerId}/segmentation/genus-preview', [DroneImageryController::class, 'previewGenusSuggestion'])
        ->whereNumber('layerId')
        ->middleware('throttle:120,1')
        ->name('drone-imagery.segmentation.genus-preview');
    Route::post('/drone-imagery/{layerId}/confirm', [DroneImageryController::class, 'confirm'])
        ->whereNumber('layerId')
        ->middleware('throttle:10,1')
        ->name('drone-imagery.confirm');
    Route::get('/drone-imagery/{layerId}', [DroneImageryController::class, 'show'])
        ->whereNumber('layerId')
        ->name('drone-imagery.show');

    // Profile routes (accessible to all authenticated users)
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.updatePassword');
    // Residents whose expert application was rejected can send a new one.
    Route::post('/profile/expert-application', [ProfileController::class, 'reapplyExpert'])
        ->middleware('throttle:5,1')
        ->name('profile.expert-application');
    // Device (Web Push) notifications for the current browser, turned on/off on My Profile.
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('push-subscriptions.destroy');

    // Help & user guide (sections shown depend on the user's role)
    Route::view('/guide', 'guide')->name('guide');

    // "Send Feedback" pop-up in the sidebar; admins review it under /admin/feedback.
    Route::post('/feedback', [FeedbackController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('feedback.store');

    // Map viewer & Delineate studio (accessible to authenticated users)
    Route::get('/map', [EndUserController::class, 'map'])->name('map');
    Route::get('/delineate', [EndUserController::class, 'delineate'])->name('delineate');
    // The map's delineations as JSON: the map redraws from it right after a save, edit, review or removal.
    Route::get('/map/delineations', [EndUserController::class, 'mapDelineations'])
        ->middleware('throttle:120,1')
        ->name('map.delineations');
    Route::get('/delineations/{delineation}/annual-observations', [DelineationAnnualObservationController::class, 'index'])
        ->name('delineations.annual-observations.index');
    Route::post('/delineations/location-preview', [EndUserController::class, 'previewDelineationLocation'])
        ->middleware('throttle:60,1')
        ->name('delineations.location-preview');
    // Edit the boundary of an existing saved delineation (experts: resident/own records; residents: own pending records)
    Route::put('/delineations/{delineation}/boundary', [EndUserController::class, 'updateDelineationBoundary'])
        ->name('delineations.boundary.update');

    // Notification routes (accessible to all authenticated users)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    // Live updates for the bell badge and pop-ups (partials/live-notifications), asked every ~20 s.
    Route::get('/notifications/poll', [NotificationController::class, 'poll'])
        ->middleware('throttle:30,1')
        ->name('notifications.poll');
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-as-read');
    Route::delete('/notifications', [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Dashboard "Field conditions" card (residents and experts): weather forecast from Open-Meteo, cached.
    Route::get('/dashboard/field-conditions', [EndUserController::class, 'fieldConditions'])
        ->middleware('throttle:30,1')
        ->name('dashboard.field-conditions');

    // Resident dashboard - RESTRICTED TO END_USER ROLE ONLY
    Route::middleware('end_user')->get('/dashboard', [EndUserController::class, 'dashboard'])->name('dashboard');
    Route::middleware('end_user')->post('/delineations', [EndUserController::class, 'storeDelineation'])->name('delineations.store');
    Route::middleware('end_user')->put('/delineations/{delineation}/revision', [EndUserController::class, 'reviseRejectedDelineation'])->name('delineations.revise');
    Route::middleware('end_user')->delete('/delineations/{delineation}', [EndUserController::class, 'destroyDelineation'])->name('delineations.destroy');

    // Expert routes - review and approve resident delineations
    Route::middleware('expert')->prefix('expert')->group(function () {
        Route::get('/dashboard', [ExpertController::class, 'dashboard'])->name('expert.dashboard');
        Route::get('/map', [ExpertController::class, 'map'])->name('expert.map');
        Route::get('/delineate', [ExpertController::class, 'delineate'])->name('expert.delineate');
        Route::post('/delineations', [ExpertController::class, 'storeDelineation'])->name('expert.delineations.store');
        Route::post('/delineations/{delineation}/approve', [ExpertController::class, 'approve'])->name('expert.delineations.approve');
        Route::post('/delineations/{delineation}/reject', [ExpertController::class, 'reject'])->name('expert.delineations.reject');
        Route::delete('/delineations/{delineation}', [ExpertController::class, 'destroyDelineation'])->name('expert.delineations.destroy');
        Route::get('/recommendations', [PlantingRecommendationController::class, 'index'])
            ->name('expert.recommendations.index');
        Route::post('/recommendations', [PlantingRecommendationController::class, 'store'])
            ->name('expert.recommendations.store');
        Route::put('/recommendations/{recommendation}', [PlantingRecommendationController::class, 'update'])
            ->name('expert.recommendations.update');
        Route::delete('/recommendations/{recommendation}', [PlantingRecommendationController::class, 'destroy'])
            ->name('expert.recommendations.destroy');
    });

    // Admin routes - RESTRICTED TO ADMIN ROLE ONLY
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        // Datasets were merged into Map Layers (details and exports).
        Route::redirect('/datasets', '/admin/map-layers');
        Route::get('/models', [AdminController::class, 'models'])->name('admin.models');
        Route::post('/models', [AdminController::class, 'uploadModel'])->name('admin.models.upload');
        Route::post('/models/{model}/activate', [AdminController::class, 'activateModel'])
            ->whereNumber('model')
            ->name('admin.models.activate');
        Route::put('/models/{model}/metrics', [AdminController::class, 'updateModelMetrics'])
            ->whereNumber('model')
            ->name('admin.models.metrics');
        Route::post('/models/{model}/metrics/from-checkpoint', [AdminController::class, 'readModelMetricsFromCheckpoint'])
            ->whereNumber('model')
            ->name('admin.models.metrics.checkpoint');
        Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
        Route::get('/users/{user}', [AdminController::class, 'show'])->name('admin.users.show');
        Route::get('/users/{user}/edit', [AdminController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{user}', [AdminController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{user}', [AdminController::class, 'destroy'])->name('admin.users.destroy');
        Route::get('/map-layers', [MapLayerController::class, 'index'])->name('admin.map-layers.index');
        Route::get('/map-layers/export', [MapLayerController::class, 'export'])
            ->middleware('throttle:20,1,admin.map-layers.export')
            ->name('admin.map-layers.export');
        Route::put('/map-layers/delineations/{delineation}', [MapLayerController::class, 'updateDelineation'])
            ->name('admin.map-layers.delineations.update');
        Route::post('/map-layers/delineations/{delineation}/hide', [MapLayerController::class, 'hideDelineation'])
            ->name('admin.map-layers.delineations.hide');
        Route::post('/map-layers/delineations/{delineation}/restore', [MapLayerController::class, 'restoreDelineation'])
            ->name('admin.map-layers.delineations.restore');
        Route::delete('/map-layers/delineations/{delineation}', [MapLayerController::class, 'destroyDelineation'])
            ->name('admin.map-layers.delineations.destroy');
        Route::put('/map-layers/imagery/{layer}', [MapLayerController::class, 'updateImagery'])
            ->name('admin.map-layers.imagery.update');
        Route::post('/map-layers/imagery/{layer}/reprocess', [MapLayerController::class, 'reprocessImagery'])
            ->name('admin.map-layers.imagery.reprocess');
        Route::post('/map-layers/imagery/{layer}/segment', [MapLayerController::class, 'resegmentImagery'])
            ->name('admin.map-layers.imagery.segment');
        Route::delete('/map-layers/imagery/{layer}', [MapLayerController::class, 'destroyImagery'])
            ->name('admin.map-layers.imagery.destroy');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');
        Route::get('/activity-logs/{activity}', [ActivityLogController::class, 'show'])
            ->whereNumber('activity')
            ->name('admin.activity-logs.show');
        Route::post('/users/{user}/expert-application/approve', [AdminController::class, 'approveExpertApplication'])
            ->name('admin.expert-applications.approve');
        Route::post('/users/{user}/expert-application/reject', [AdminController::class, 'rejectExpertApplication'])
            ->name('admin.expert-applications.reject');
        Route::get('/users/{user}/expert-application/certificate', [AdminController::class, 'expertCertificate'])
            ->name('admin.expert-applications.certificate');
        Route::get('/feedback', [FeedbackController::class, 'index'])->name('admin.feedback.index');
        Route::put('/feedback/{feedback}', [FeedbackController::class, 'update'])
            ->whereNumber('feedback')
            ->name('admin.feedback.update');
        Route::delete('/feedback/{feedback}', [FeedbackController::class, 'destroy'])
            ->whereNumber('feedback')
            ->name('admin.feedback.destroy');
        Route::get('/feedback/{feedback}/screenshot', [FeedbackController::class, 'screenshot'])
            ->whereNumber('feedback')
            ->name('admin.feedback.screenshot');
    });
});
