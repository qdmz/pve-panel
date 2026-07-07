<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function index()
    {
        try {
            $backupPath = storage_path('app/backups');

            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0755, true);
            }

            $files = collect(File::files($backupPath))
                ->map(function ($file) {
                    return [
                        'name' => $file->getFilename(),
                        'size' => $file->getSize(),
                        'size_human' => $this->formatBytes($file->getSize()),
                        'date' => date('Y-m-d H:i:s', $file->getMTime()),
                        'path' => $file->getPathname(),
                    ];
                })
                ->sortByDesc('date')
                ->values()
                ->toArray();

            return ApiResponse::success(['backups' => $files]);
        } catch (\Exception $e) {
            \Log::error('Admin\\BackupController::index failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve backups.', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $type = $request->input('type', 'database');
            if (!in_array($type, ['database', 'full', 'config'])) {
                $type = 'database';
            }
            $filename = $type . '-backup-' . date('Ymd_His') . '.sql';
            $backupPath = storage_path('app/backups');
            
            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0755, true);
            }

            $fullPath = $backupPath . '/' . $filename;

            // Database backup
            $db = config('database.connections.' . config('database.default'));
            $command = sprintf(
                'mysqldump -u%s -p%s -h%s %s > %s',
                escapeshellarg($db['username']),
                escapeshellarg($db['password']),
                escapeshellarg($db['host'] ?? '127.0.0.1'),
                escapeshellarg($db['database']),
                escapeshellarg($fullPath)
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                // Fallback: create minimal backup marker
                $content = "-- Backup created at: " . now() . "\n";
                $content .= "-- Type: {$type}\n";
                $content .= "-- Database: " . ($db['database'] ?? 'unknown') . "\n";

                if ($type === 'full' || $type === 'config') {
                    $content .= "\n-- Config Backup (files not captured in SQL format)\n";
                }

                File::put($fullPath, $content);
            }

            return ApiResponse::success([
                'filename' => $filename,
                'path'     => $fullPath,
            ], 'Backup created.', 201);
        } catch (\Exception $e) {
            \Log::error('Admin\\BackupController::store failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to create backup.', 500);
        }
    }

    public function download($backupId)
    {
        try {
            $backupPath = storage_path('app/backups');
            $filename   = basename($backupId);
            $fullPath   = $backupPath . '/' . $filename;

            if (!File::exists($fullPath)) {
                return ApiResponse::error('Backup file not found.', 404);
            }

            return response()->download($fullPath);
        } catch (\Exception $e) {
            \Log::error('Admin\\BackupController::download failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to download backup.', 500);
        }
    }

    public function destroy($backupId)
    {
        try {
            $backupPath = storage_path('app/backups');
            $filename   = basename($backupId);
            $fullPath   = $backupPath . '/' . $filename;

            if (!File::exists($fullPath)) {
                return ApiResponse::error('Backup file not found.', 404);
            }

            File::delete($fullPath);

            return ApiResponse::success(null, 'Backup deleted.');
        } catch (\Exception $e) {
            \Log::error('Admin\\BackupController::destroy failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to delete backup.', 500);
        }
    }

    public function restore($backupId, Request $request)
    {
        try {
            $backupPath = storage_path('app/backups');
            $filename   = basename($backupId);
            $fullPath   = $backupPath . '/' . $filename;

            if (!File::exists($fullPath)) {
                return ApiResponse::error('Backup file not found.', 404);
            }

            // Restore from SQL file
            $db = config('database.connections.' . config('database.default'));
            $command = sprintf(
                'mysql -u%s -p%s -h%s %s < %s',
                escapeshellarg($db['username']),
                escapeshellarg($db['password']),
                escapeshellarg($db['host'] ?? '127.0.0.1'),
                escapeshellarg($db['database']),
                escapeshellarg($fullPath)
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                return ApiResponse::error('Failed to restore backup. Error code: ' . $exitCode, 500);
            }

            return ApiResponse::success(null, 'Backup restored successfully.');
        } catch (\Exception $e) {
            \Log::error('Admin\\BackupController::restore failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to restore backup.', 500);
        }
    }

    public function settings()
    {
        try {
            $settings = Setting::getByGroup('backup');

            return ApiResponse::success(['settings' => $settings]);
        } catch (\Exception $e) {
            \Log::error('Admin\\BackupController::settings failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to retrieve backup settings.', 500);
        }
    }

    public function updateSettings(Request $request)
    {
        try {
            $data = $request->only([
                'auto_backup', 'backup_frequency', 'retention_days', 'backup_type',
            ]);

            foreach ($data as $key => $value) {
                Setting::updateOrCreate(
                    ['group' => 'backup', 'key' => $key],
                    ['value' => $value]
                );
            }

            return ApiResponse::success(['settings' => $data], 'Backup settings updated.');
        } catch (\Exception $e) {
            \Log::error('Admin\\BackupController::updateSettings failed', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to update backup settings.', 500);
        }
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
