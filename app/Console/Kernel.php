<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\CheckVmExpiry::class,
        Commands\SyncNodeData::class,
        Commands\CleanupBackups::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Check VM expiry and send notifications every hour
        $schedule->command('vms:check-expiry')->hourly();

        // Sync node data every 30 minutes
        $schedule->command('nodes:sync')->everyThirtyMinutes();

        // Cleanup old backups daily at midnight
        $schedule->command('backups:cleanup --force')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
