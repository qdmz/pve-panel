<?php

namespace App\Console\Commands;

use App\Jobs\CheckExpiryJob;
use Illuminate\Console\Command;

class CheckVmExpiry extends Command
{
    protected $signature = 'vms:check-expiry';
    protected $description = 'Check for expiring and expired VMs, send notifications and auto-suspend';

    public function handle(): int
    {
        $this->info('Checking VM expiry...');

        CheckExpiryJob::dispatch();

        $this->info('Expiry check job dispatched.');

        return Command::SUCCESS;
    }
}
