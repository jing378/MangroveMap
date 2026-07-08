<?php

use App\Http\Controllers\PublicController;
use App\Http\Controllers\EndUserController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ClassifyController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ExpertController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

    Route::post('/login', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store']);

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

    Route::post('/register', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'store']);

    Route::get('/forgot-password', function () {
        return view('auth.forgot-password');
    })->name('password.request');

    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('/reset-password/{token}', function ($token) {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request('email'),
        ]);
    })->name('password.reset');

    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->name('password.update');
});

Route::post('/logout', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

// Email Verification Routes

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', function () {
        return view('auth.verify-email');
    })->name('verification.notice');
// Send email api
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('message', 'Verification link sent!');
    })->middleware('throttle:6,1')->name('verification.send');

// Email verification

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        return redirect(Auth::user()->homeRoute())
            ->with('success', 'Email verified successfully!');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
});

// Public routes
Route::get('/', function () {
    if (Auth::check()) {
        return redirect(Auth::user()->homeRoute());
    }
    return app(PublicController::class)->index();
})->name('index');

Route::middleware(['auth', 'verified'])->group(function () {
    // Profile routes (accessible to all authenticated users)
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.updatePassword');

    // Map viewer (accessible to all authenticated users)
    Route::get('/map', [MapController::class, 'show'])->name('map');

    // Image classification (accessible to all authenticated users)
    Route::get('/classify', [ClassifyController::class, 'create'])->name('classify');
    Route::post('/classify', [ClassifyController::class, 'store'])->name('classify.store');
    Route::get('/classify/{analysis}', [ClassifyController::class, 'results'])->name('classify.results');

    // Notification routes (accessible to all authenticated users)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-as-read');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Resident dashboard - RESTRICTED TO END_USER ROLE ONLY
    Route::middleware('end_user')->get('/dashboard', [EndUserController::class, 'dashboard'])->name('dashboard');
    Route::middleware('end_user')->post('/delineations', [EndUserController::class, 'storeDelineation'])->name('delineations.store');
    Route::middleware('end_user')->delete('/delineations/{delineation}', [EndUserController::class, 'destroyDelineation'])->name('delineations.destroy');

    // Expert routes - review and approve resident delineations
    Route::middleware('expert')->prefix('expert')->group(function () {
        Route::get('/dashboard', [ExpertController::class, 'dashboard'])->name('expert.dashboard');
        Route::post('/delineations', [ExpertController::class, 'storeDelineation'])->name('expert.delineations.store');
        Route::post('/delineations/{delineation}/approve', [ExpertController::class, 'approve'])->name('expert.delineations.approve');
        Route::post('/delineations/{delineation}/reject', [ExpertController::class, 'reject'])->name('expert.delineations.reject');
    });

    // Admin routes - RESTRICTED TO ADMIN ROLE ONLY
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/datasets', [AdminController::class, 'datasets'])->name('admin.datasets');
        Route::get('/models', [AdminController::class, 'models'])->name('admin.models');
        Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
        Route::get('/users/{user}', [AdminController::class, 'show'])->name('admin.users.show');
        Route::get('/users/{user}/edit', [AdminController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{user}', [AdminController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{user}', [AdminController::class, 'destroy'])->name('admin.users.destroy');
    });
});
