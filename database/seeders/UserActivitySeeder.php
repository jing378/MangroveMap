<?php

namespace Database\Seeders;

use App\Models\Analysis;
use App\Models\Delineation;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Database\Seeder;

class UserActivitySeeder extends Seeder
{
    public function run(): void
    {
        UserActivity::query()->delete();

        foreach (Analysis::with('user')->get() as $analysis) {
            if (!$analysis->user_id) {
                continue;
            }
            UserActivity::recordAnalysis($analysis, $analysis->created_at);
        }

        foreach (Delineation::with(['user', 'approvedBy', 'rejectedBy'])->get() as $delineation) {
            if (!$delineation->user_id) {
                continue;
            }

            if ($delineation->is_approved && $delineation->approved_by) {
                UserActivity::recordDelineationSubmitted($delineation);
                $expert = $delineation->approvedBy ?? User::find($delineation->approved_by);
                if ($expert) {
                    UserActivity::recordDelineationApproved($delineation, $expert);
                }
            } elseif ($delineation->is_rejected && $delineation->rejected_by) {
                UserActivity::recordDelineationSubmitted($delineation);
                $expert = $delineation->rejectedBy ?? User::find($delineation->rejected_by);
                if ($expert) {
                    UserActivity::recordDelineationRejected($delineation, $expert);
                }
            } else {
                UserActivity::recordDelineationSubmitted($delineation);
            }
        }

        $admin = User::where('email', 'admin@example.com')->first();
        $demo = User::where('email', 'demo@example.com')->first();
        $expert = User::where('email', 'expert@example.com')->first();

        foreach ([$admin, $demo, $expert] as $user) {
            if (!$user) {
                continue;
            }
            UserActivity::record(
                $user,
                'Logged in',
                'Authentication',
                'success',
                null,
                now()->subDays(2),
            );
        }

        if ($demo) {
            UserActivity::record(
                $demo,
                'Account verified',
                'User Management',
                'success',
                null,
                $demo->email_verified_at ?? now()->subMonth(),
            );
        }
    }
}
