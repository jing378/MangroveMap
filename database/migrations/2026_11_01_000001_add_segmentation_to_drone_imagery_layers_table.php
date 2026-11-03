<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drone_imagery_layers', function (Blueprint $table) {
            // null = never run; queued | processing | ready | failed
            $table->string('segmentation_status', 20)->nullable()->after('processed_at');
            $table->string('segmentation_path')->nullable()->after('segmentation_status');
            $table->json('segmentation_stats')->nullable()->after('segmentation_path');
            $table->text('segmentation_error')->nullable()->after('segmentation_stats');
            $table->timestamp('segmented_at')->nullable()->after('segmentation_error');
        });
    }

    public function down(): void
    {
        Schema::table('drone_imagery_layers', function (Blueprint $table) {
            $table->dropColumn([
                'segmentation_status',
                'segmentation_path',
                'segmentation_stats',
                'segmentation_error',
                'segmented_at',
            ]);
        });
    }
};
