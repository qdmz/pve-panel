<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupService
{
    private string $backupPath;

    public function __construct()
    {
        $this->backupPath = storage_path('app/backups');
        if (!File::exists($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
        }
    }

    /**
     * Create a new backup.
     */
    public function createBackup(string $type = 'full'): ?Backup
    {
        try {
            $filename = 'backup_' . $type . '_' . date('YmdHis') . '.zip';
            $filePath = $this->backupPath . '/' . $filename;

            $backup = Backup::create([
                'filename' => $filename,
                'path' => $filePath,
                'size' => 0,
                'type' => $type,
                'status' => 'in_progress',
            ]);

            switch ($type) {
                case 'db':
                    $this->backupDatabase($filePath);
                    break;
                case 'files':
                    $this->backupFiles($filePath);
                    break;
                case 'full':
                default:
                    $this->backupFull($filePath);
                    break;
            }

            if (!File::exists($filePath)) {
                throw new \RuntimeException('Backup file was not created');
            }

            $size = File::size($filePath);

            $backup->update([
                'status' => 'success',
                'size' => $size,
            ]);

            Log::info("Backup created: {$filename} ({$this->formatSize($size)})");

            return $backup;

        } catch (\Exception $e) {
            Log::error('Backup creation failed: ' . $e->getMessage());

            if (isset($backup)) {
                $backup->update([
                    'status' => 'failed',
                    'notes' => $e->getMessage(),
                ]);
            }

            return null;
        }
    }

    /**
     * Restore from a backup.
     */
    public function restoreBackup(int $backupId): array
    {
        $backup = Backup::find($backupId);

        if (!$backup) {
            return ['success' => false, 'message' => '备份记录不存在'];
        }

        if (!File::exists($backup->path)) {
            return ['success' => false, 'message' => '备份文件不存在'];
        }

        try {
            $tempDir = storage_path('app/backups/restore_' . time());
            File::makeDirectory($tempDir, 0755, true);

            // Extract zip
            $zip = new \ZipArchive();
            if ($zip->open($backup->path) === true) {
                $zip->extractTo($tempDir);
                $zip->close();
            } else {
                File::deleteDirectory($tempDir);
                return ['success' => false, 'message' => '无法解压备份文件'];
            }

            // Check for SQL dump and restore
            $sqlFile = $tempDir . '/database.sql';
            if (File::exists($sqlFile)) {
                $this->restoreDatabase($sqlFile);
            }

            // Check for file backups
            $filesSource = $tempDir . '/files';
            if (File::isDirectory($filesSource)) {
                $this->restoreFiles($filesSource);
            }

            // Cleanup
            File::deleteDirectory($tempDir);

            Log::info("Backup restored from: {$backup->filename}");

            return ['success' => true, 'message' => '备份恢复成功'];

        } catch (\Exception $e) {
            Log::error('Backup restore failed: ' . $e->getMessage());
            return ['success' => false, 'message' => '恢复失败: ' . $e->getMessage()];
        }
    }

    /**
     * Delete a backup record and file.
     */
    public function deleteBackup(int $backupId): bool
    {
        $backup = Backup::find($backupId);

        if (!$backup) {
            return false;
        }

        try {
            if (File::exists($backup->path)) {
                File::delete($backup->path);
            }

            $backup->delete();

            Log::info("Backup deleted: {$backup->filename}");

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to delete backup: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * List all backups.
     */
    public function getBackupList(): array
    {
        return Backup::orderBy('created_at', 'desc')
            ->get()
            ->map(function ($backup) {
                return [
                    'id' => $backup->id,
                    'filename' => $backup->filename,
                    'type' => $backup->type,
                    'size' => $this->formatSize($backup->size),
                    'size_bytes' => $backup->size,
                    'status' => $backup->status,
                    'notes' => $backup->notes,
                    'created_at' => $backup->created_at->format('Y-m-d H:i:s'),
                ];
            })
            ->toArray();
    }

    /**
     * Get storage information.
     */
    public function getStorageInfo(): array
    {
        $backups = Backup::where('status', 'success')->get();

        return [
            'total_count' => $backups->count(),
            'total_size' => $this->formatSize($backups->sum('size')),
            'total_size_bytes' => $backups->sum('size'),
            'disk_free' => $this->formatSize(disk_free_space($this->backupPath)),
            'disk_total' => $this->formatSize(disk_total_space($this->backupPath)),
            'path' => $this->backupPath,
        ];
    }

    /**
     * Generate download response for a backup.
     */
    public function downloadBackup(int $backupId): ?array
    {
        $backup = Backup::find($backupId);

        if (!$backup || !File::exists($backup->path)) {
            return null;
        }

        return [
            'path' => $backup->path,
            'filename' => $backup->filename,
            'mime' => 'application/zip',
        ];
    }

    /**
     * Create a full backup (database + files).
     */
    private function backupFull(string $filePath): void
    {
        $tempDir = storage_path('app/backups/temp_' . time());
        File::makeDirectory($tempDir, 0755, true);

        try {
            // Backup database
            $this->dumpDatabase($tempDir . '/database.sql');

            // Backup key directories
            $dirsToBackup = [
                storage_path('app/public') => 'storage',
                base_path('.env') => '.env',
            ];

            $filesDir = $tempDir . '/files';
            File::makeDirectory($filesDir, 0755, true);

            foreach ($dirsToBackup as $source => $name) {
                if (File::isDirectory($source)) {
                    File::copyDirectory($source, $filesDir . '/' . $name);
                } elseif (File::exists($source)) {
                    File::copy($source, $filesDir . '/' . $name);
                }
            }

            // Create ZIP
            $this->createZip($tempDir, $filePath);

        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * Database-only backup.
     */
    private function backupDatabase(string $filePath): void
    {
        $tempDir = storage_path('app/backups/temp_db_' . time());
        File::makeDirectory($tempDir, 0755, true);

        try {
            $this->dumpDatabase($tempDir . '/database.sql');
            $this->createZip($tempDir, $filePath);
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * Files-only backup.
     */
    private function backupFiles(string $filePath): void
    {
        $tempDir = storage_path('app/backups/temp_files_' . time());
        File::makeDirectory($tempDir, 0755, true);

        try {
            $dirsToBackup = [
                storage_path('app/public') => 'storage',
                base_path('.env') => '.env',
                public_path('uploads') => 'uploads',
            ];

            $filesDir = $tempDir . '/files';
            File::makeDirectory($filesDir, 0755, true);

            foreach ($dirsToBackup as $source => $name) {
                if (File::isDirectory($source)) {
                    File::copyDirectory($source, $filesDir . '/' . $name);
                } elseif (File::exists($source)) {
                    File::copy($source, $filesDir . '/' . $name);
                }
            }

            $this->createZip($tempDir, $filePath);
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * Execute mysqldump for database backup.
     */
    private function dumpDatabase(string $outputPath): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $host = $config['host'];
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];

        // Use mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            // Fallback: Try Laravel's built-in schema dump
            Artisan::call('schema:dump', [
                '--path' => dirname($outputPath),
            ]);

            $schemaFile = storage_path('app/' . config('database.default') . '-schema.dump');
            if (File::exists($schemaFile)) {
                File::move($schemaFile, $outputPath);
            }
        }

        if (!File::exists($outputPath)) {
            throw new \RuntimeException('Database dump failed');
        }
    }

    /**
     * Restore database from SQL dump.
     */
    private function restoreDatabase(string $sqlFile): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $host = $config['host'];
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];

        $command = sprintf(
            'mysql --host=%s --port=%s --user=%s --password=%s %s < %s 2>&1',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($sqlFile)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Database restore failed: ' . implode("\n", $output));
        }
    }

    /**
     * Restore files from backup.
     */
    private function restoreFiles(string $sourceDir): void
    {
        $dirs = File::directories($sourceDir);

        foreach ($dirs as $dir) {
            $name = basename($dir);
            $target = null;
            if ($name === 'storage') {
                $target = storage_path('app/public');
            } elseif ($name === 'uploads') {
                $target = public_path('uploads');
            }

            if ($target && File::isDirectory($dir)) {
                File::copyDirectory($dir, $target);
            }
        }

        $envFile = $sourceDir . '/.env';
        if (File::exists($envFile)) {
            File::copy($envFile, base_path('.env'));
        }
    }

    /**
     * Create a ZIP archive from a directory.
     */
    private function createZip(string $sourceDir, string $outputPath): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create ZIP file: ' . $outputPath);
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    /**
     * Format bytes to human-readable size.
     */
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
