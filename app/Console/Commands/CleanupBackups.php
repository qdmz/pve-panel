<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupBackups extends Command
{
    protected $signature = 'backups:cleanup {--force : Force cleanup without confirmation}';
    protected $description = 'Remove old backups based on retention policy';

    public function handle(): int
    {
        $backupPath  = storage_path('app/backups');

        if (!File::exists($backupPath)) {
            $this->info('Backup directory does not exist. Nothing to clean up.');
            return Command::SUCCESS;
        }

        $retentionDays = (int) Setting::get('backup.retention_days', 30);

        $this->info("Retention policy: {$retentionDays} days");

        $cutoffDate = now()->subDays($retentionDays);
        $deletedCount = 0;

        $files = File::files($backupPath);

        foreach ($files as $file) {
            $mtime = $file->getMTime();

            if ($mtime < $cutoffDate->timestamp) {
                $filename = $file->getFilename();
                $filesize = $this->formatBytes($file->getSize());

                if ($this->option('force') || $this->confirm("Delete backup '{$filename}' ({$filesize})?")) {
                    File::delete($file->getPathname());
                    $this->line("  Deleted: {$filename}");
                    $deletedCount++;
                }
            }
        }

        $this->info("Cleanup completed. {$deletedCount} backup(s) deleted.");

        return Command::SUCCESS;
    }

    protected function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
