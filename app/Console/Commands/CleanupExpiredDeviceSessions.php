<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;

class CleanupExpiredDeviceSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:cleanup-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired device sessions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of expired device sessions...');
        
        $cleanedCount = Device::cleanupExpiredSessions();
        
        $this->info("Cleaned up {$cleanedCount} expired device sessions.");
        
        return 0;
    }
}
