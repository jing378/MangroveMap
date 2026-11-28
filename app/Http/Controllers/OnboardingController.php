<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The welcome walkthrough (partials/onboarding-tour): remembers on the account that the user finished or skipped
 * it, so it stops opening by itself. Opening it again from Help & Guide needs no request.
 */
class OnboardingController extends Controller
{
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->onboarded_at === null) {
            $user->forceFill(['onboarded_at' => now()])->save();
        }

        return response()->json(['onboarded_at' => $user->onboarded_at?->toIso8601String()]);
    }
}
