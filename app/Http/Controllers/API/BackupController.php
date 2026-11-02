<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    protected BackupService $backups;

    public function __construct(BackupService $backups)
    {
        $this->backups = $backups;
    }

    /**
     * GET /api/v1/admin/backup/list
     * List all backup files in storage/app/backups.
     */
    public function index()
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        try {
            $rows = $this->backups->listBackups();
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to list backups: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'backups' => $rows,
                'total' => count($rows),
            ],
        ], 200);
    }

    /**
     * GET /api/v1/admin/backup/download/{filename}
     * Stream a backup file as an attachment.
     */
    public function download($filename)
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        try {
            $path = $this->backups->downloadPath($filename);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }

        AuditService::log(
            action: 'BACKUP_DOWNLOADED',
            tableName: 'system',
            recordId: 0,
            newValues: ['filename' => $filename],
            logType: 'info'
        );

        return response()->download($path, basename($path));
    }

    /**
     * DELETE /api/v1/admin/backup/delete/{filename}
     * Delete a backup file. Password required.
     */
    public function destroy(Request $request, $filename)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'director']);
        $this->verifyPassword($request, $user);

        try {
            $this->backups->deleteBackup($filename);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        AuditService::log(
            action: 'BACKUP_DELETED',
            tableName: 'system',
            recordId: $user->id,
            oldValues: ['filename' => $filename],
            logType: 'warning'
        );

        return response()->json([
            'status' => 'success',
            'message' => "Backup '{$filename}' deleted",
        ], 200);
    }

    /**
     * POST /api/v1/admin/backup/restore/{filename}
     * Restore from a backup. Super-admin only, password required,
     * filename must match exactly. Always creates a pre-restore safety dump.
     */
    public function restore(Request $request, $filename)
    {
        $user = Auth::user();

        // this is the most dangerous endpoint in the portal — super_admin only
        if ($user->role !== 'super_admin') {
            AuditService::logWarning('BACKUP_RESTORE_FORBIDDEN', 'system', 0, [
                'actor_id' => $user->id,
                'role' => $user->role,
                'filename' => $filename,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Only Super Admins can restore backups.',
            ], 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'password' => 'required|string',
            'confirm_filename' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Password is incorrect',
            ], 422);
        }

        if ($request->confirm_filename !== $filename) {
            return response()->json([
                'status' => 'error',
                'message' => 'Filename confirmation does not match.',
            ], 422);
        }

        AuditService::log(
            action: 'BACKUP_RESTORE_STARTED',
            tableName: 'system',
            recordId: $user->id,
            newValues: ['filename' => $filename],
            logType: 'warning',
            severity: 'critical'
        );

        try {
            $result = $this->backups->restore($filename, $user);
        } catch (\Throwable $e) {
            AuditService::log(
                action: 'BACKUP_RESTORE_FAILED',
                tableName: 'system',
                recordId: $user->id,
                newValues: ['filename' => $filename, 'error' => $e->getMessage()],
                logType: 'error',
                severity: 'critical'
            );

            return response()->json([
                'status' => 'error',
                'message' => 'Restore failed: ' . $e->getMessage(),
            ], 500);
        }

        AuditService::log(
            action: 'BACKUP_RESTORE_COMPLETED',
            tableName: 'system',
            recordId: $user->id,
            newValues: $result,
            logType: 'success',
            severity: 'critical'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Database restored from ' . $filename,
            'data' => $result,
        ], 200);
    }

    // ---- helpers ----

    protected function requireRole(array $roles): User
    {
        $user = Auth::user();

        if (!in_array($user->role, $roles, true)) {
            AuditService::logWarning('BACKUP_ACCESS_DENIED', 'system', 0, [
                'actor_id' => $user->id,
                'required' => $roles,
                'actual' => $user->role,
            ]);
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }

        return $user;
    }

    protected function verifyPassword(Request $request, User $user): void
    {
        if (!Hash::check((string) $request->input('password'), $user->password)) {
            abort(response()->json(['status' => 'error', 'message' => 'Password is incorrect'], 422));
        }
    }
}