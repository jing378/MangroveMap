<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table): void {
            // What the accuracy figure measures (e.g. IoU for the U-Net, mAP50 for YOLO); entered by the admin.
            $table->string('accuracy_metric', 40)->nullable()->after('accuracy');
        });
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table): void {
            $table->dropColumn('accuracy_metric');
        });
    }
};
