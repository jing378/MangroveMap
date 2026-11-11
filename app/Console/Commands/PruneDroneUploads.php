<?php

namespace App\Console\Commands;

use App\Http\Controllers\DroneImageryUploadController;
use Illuminate\Console\Command;

class PruneDroneUploads extends Command
{
    protected $signature = 'drone-imagery:prune-uploads';

    protected $description = 'Delete chunked drone imagery uploads that were abandoned more than a day ago';

    public function handle(): int
    {
        $removed = DroneImageryUploadController::pruneStaleSessions();
        $this->info("Removed {$removed} abandoned upload(s).");

        return self::SUCCESS;
    }
}
