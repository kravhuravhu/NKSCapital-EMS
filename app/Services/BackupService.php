<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupService
{
    protected string $disk = 'local';
    protected string $dir = 'backups';

    public function createBackup(string $prefix = 'backup'): array
    {
        $driver = DB::getDriverName();
        $canMysqldump = in_array($driver, ['mysql', 'mariadb'], true) && $this->hasBinary('mysqldump');

        if ($driver === 'sqlite') {
            return $this->createSqliteBackup($prefix);
        }

        if ($canMysqldump) {
            return $this->createMysqlBackup($prefix);
        }

        // mysql without mysqldump — PHP fallback
        return $this->createPhpBackup($prefix);
    }

    public function listBackups(): array
    {
        if (!Storage::disk($this->disk)->exists($this->dir)) {
            return [];
        }

        $files = Storage::disk($this->disk)->files($this->dir);
        $rows = [];

        foreach ($files as $file) {
            $filename = basename($file);

            if (!preg_match('/^[a-z0-9-]+\.(sql|sqlite|phpdump)$/i', $filename)) {
                continue;
            }

            $size = Storage::disk($this->disk)->size($file);
            $modified = Storage::disk($this->disk)->lastModified($file);

            $rows[] = [
                'filename' => $filename,
                'format' => pathinfo($filename, PATHINFO_EXTENSION),
                'size' => $size,
                'size_human' => $this->humanSize($size),
                'created_at' => date('Y-m-d H:i:s', $modified),
                'is_pre_restore' => str_starts_with($filename, 'pre-restore-'),
                'restorable' => in_array(pathinfo($filename, PATHINFO_EXTENSION), ['sql', 'sqlite'], true),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $rows;
    }

    public function deleteBackup(string $filename): void
    {
        $filename = $this->sanitizeFilename($filename);
        $path = $this->dir . '/' . $filename;

        if (!Storage::disk($this->disk)->exists($path)) {
            throw new \RuntimeException("Backup '{$filename}' not found.");
        }

        Storage::disk($this->disk)->delete($path);
    }

    public function downloadPath(string $filename): string
    {
        $filename = $this->sanitizeFilename($filename);
        $path = $this->dir . '/' . $filename;

        if (!Storage::disk($this->disk)->exists($path)) {
            throw new \RuntimeException("Backup '{$filename}' not found.");
        }

        return Storage::disk($this->disk)->path($path);
    }

    public function restore(string $filename, User $actor): array
    {
        $filename = $this->sanitizeFilename($filename);
        $path = $this->dir . '/' . $filename;

        if (!Storage::disk($this->disk)->exists($path)) {
            throw new \RuntimeException("Backup '{$filename}' not found.");
        }

        // only sql and sqlite files are restorable
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (!in_array($extension, ['sql', 'sqlite'], true)) {
            throw new \RuntimeException("Restore is only supported for .sql and .sqlite backups. '{$filename}' is a {$extension} file.");
        }

        // safety: always create a pre-restore dump so the restore can be reversed
        $safety = $this->createBackup('pre-restore');

        $fullPath = Storage::disk($this->disk)->path($path);

        if ($extension === 'sqlite') {
            $this->restoreSqlite($fullPath);
        } elseif ($extension === 'sql') {
            $this->restoreMysql($fullPath);
        }

        return [
            'restored_from' => $filename,
            'safety_backup' => $safety['filename'],
            'restored_at' => now()->toIso8601String(),
        ];
    }

    // ---- creators ----

    protected function createSqliteBackup(string $prefix): array
    {
        $source = database_path('database.sqlite');

        if (!file_exists($source)) {
            throw new \RuntimeException('SQLite database file not found at ' . $source);
        }

        $filename = $prefix . '-' . now()->format('Ymd-His') . '.sqlite';
        $path = $this->dir . '/' . $filename;
        $content = file_get_contents($source);

        Storage::disk($this->disk)->put($path, $content);

        return $this->backupMeta($filename, strlen($content), 'sqlite');
    }

    protected function createMysqlBackup(string $prefix): array
    {
        $filename = $prefix . '-' . now()->format('Ymd-His') . '.sql';
        $path = $this->dir . '/' . $filename;

        $conn = config('database.connections.mysql');

        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers --default-character-set=utf8mb4 %s 2>&1',
            escapeshellarg($conn['host']),
            escapeshellarg($conn['port']),
            escapeshellarg($conn['username']),
            escapeshellarg($conn['password']),
            escapeshellarg($conn['database'])
        );

        $output = shell_exec($cmd);

        if (empty($output) || !str_starts_with((string) $output, '--')) {
            throw new \RuntimeException('mysqldump failed: ' . ($output ?: 'empty output'));
        }

        Storage::disk($this->disk)->put($path, $output);

        return $this->backupMeta($filename, strlen($output), 'sql');
    }

    protected function createPhpBackup(string $prefix): array
    {
        $filename = $prefix . '-' . now()->format('Ymd-His') . '.phpdump';
        $path = $this->dir . '/' . $filename;

        $lines = [];
        $lines[] = '-- NKS EMS PHP fallback dump';
        $lines[] = '-- Created: ' . now()->toIso8601String();
        $lines[] = '-- Note: this format is for reference only. It cannot be restored via the portal.';
        $lines[] = '-- Install mysqldump on the server to enable full restorable backups.';
        $lines[] = '';

        $driver = DB::getDriverName();
        $tables = $driver === 'mysql'
            ? DB::select('SHOW TABLES')
            : DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");

        $list = array_map(fn ($row) => reset((array) $row), $tables);

        foreach ($list as $table) {
            if ($table === 'migrations') continue;

            $lines[] = "-- Table: {$table}";
            $rows = DB::table($table)->get();

            if ($rows->isEmpty()) {
                $lines[] = '-- (empty)';
                $lines[] = '';
                continue;
            }

            foreach ($rows as $row) {
                $data = (array) $row;
                $columns = array_keys($data);
                $values = array_map(function ($v) {
                    if ($v === null) return 'NULL';
                    if (is_bool($v)) return $v ? '1' : '0';
                    if (is_int($v) || is_float($v)) return $v;
                    return "'" . str_replace("'", "''", (string) $v) . "'";
                }, array_values($data));

                $lines[] = sprintf(
                    'INSERT INTO `%s` (`%s`) VALUES (%s);',
                    $table,
                    implode('`, `', $columns),
                    implode(', ', $values)
                );
            }
            $lines[] = '';
        }

        $content = implode("\n", $lines);
        Storage::disk($this->disk)->put($path, $content);

        return $this->backupMeta($filename, strlen($content), 'phpdump');
    }

    // ---- restorers ----

    protected function restoreSqlite(string $fullPath): void
    {
        $dbPath = database_path('database.sqlite');

        // close all active connections before overwriting the file
        DB::disconnect();
        DB::purge();

        $backup = file_get_contents($fullPath);

        if (!str_starts_with($backup, "SQLite format 3\0")) {
            throw new \RuntimeException('File is not a valid SQLite database.');
        }

        if (file_put_contents($dbPath, $backup) === false) {
            throw new \RuntimeException('Failed to write SQLite database file.');
        }
    }

    protected function restoreMysql(string $fullPath): void
    {
        if (!$this->hasBinary('mysql')) {
            throw new \RuntimeException('mysql client binary not found on the server. Cannot restore .sql backups.');
        }

        $conn = config('database.connections.mysql');

        $cmd = sprintf(
            'mysql --host=%s --port=%s --user=%s --password=%s %s < %s 2>&1',
            escapeshellarg($conn['host']),
            escapeshellarg($conn['port']),
            escapeshellarg($conn['username']),
            escapeshellarg($conn['password']),
            escapeshellarg($conn['database']),
            escapeshellarg($fullPath)
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('mysql restore failed: ' . implode("\n", $output));
        }
    }

    // ---- helpers ----

    protected function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);

        if (!preg_match('/^[a-z0-9-]+\.(sql|sqlite|phpdump)$/i', $filename)) {
            throw new \RuntimeException('Invalid backup filename.');
        }

        return $filename;
    }

    protected function hasBinary(string $bin): bool
    {
        $output = @shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null');

        return !empty(trim((string) $output));
    }

    protected function backupMeta(string $filename, int $size, string $format): array
    {
        return [
            'filename' => $filename,
            'format' => $format,
            'size' => $size,
            'size_human' => $this->humanSize($size),
            'created_at' => now()->toIso8601String(),
        ];
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';

        return round($bytes / 1073741824, 2) . ' GB';
    }
}