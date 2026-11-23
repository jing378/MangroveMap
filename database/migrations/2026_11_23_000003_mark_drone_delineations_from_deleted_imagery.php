<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Delineations whose drone imagery an admin deleted from Map Layers lost their drone_imagery_layer_id before
 * `source` existed, so the first backfill saw them as hand-drawn. A drone delineation shares its layer's name
 * (Delineation::booted keeps them in sync), and each such deletion is in the activity log as
 * "Deleted drone imagery: <name>": mark unlinked delineations with that name, created before the deletion.
 */
return new class extends Migration
{
    private const PREFIX = 'Deleted drone imagery: ';

    public function up(): void
    {
        DB::table('user_activities')
            ->where('subject_type', 'App\Models\DroneImageryLayer')
            ->where('activity', 'like', self::PREFIX.'%')
            ->orderBy('id')
            ->get(['activity', 'created_at'])
            ->each(function ($activity) {
                $name = trim(substr($activity->activity, strlen(self::PREFIX)));
                if ($name === '') {
                    return;
                }

                DB::table('delineations')
                    ->where('source', 'manual')
                    ->whereNull('drone_imagery_layer_id')
                    ->where('name', $name)
                    ->where('created_at', '<=', $activity->created_at)
                    ->update(['source' => 'drone']);
            });
    }

    public function down(): void
    {
        // Data fix only: nothing to undo.
    }
};
