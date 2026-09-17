<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContractService
{
    /**
     * Upload a new contract.
     */
    public function uploadContract(User $employee, UploadedFile $file, array $data, User $actor): Contract
    {
        return DB::transaction(function () use ($employee, $file, $data, $actor) {
            // Determine next version
            $lastVersion = Contract::where('user_id', $employee->id)->max('version') ?? 0;
            $nextVersion = $lastVersion + 1;

            // Store file
            $filename = 'contract-' . $employee->id . '-v' . $nextVersion . '-' . time()
                . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('contracts', $filename, 'public');
            $fileHash = hash_file('sha256', $file->getRealPath());

            $contract = Contract::create([
                'user_id' => $employee->id,
                'parent_contract_id' => $data['parent_contract_id'] ?? null,
                'version' => $nextVersion,
                'file_path' => $path,
                'file_hash' => $fileHash,
                'effective_date' => $data['effective_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'probation_end_date' => $data['probation_end_date'] ?? null,
                'salary_annual' => $data['salary_annual'] ?? null,
                'notice_period_days' => $data['notice_period_days'] ?? null,
                'position' => $data['position'] ?? $employee->position,
                'contract_type' => $data['contract_type'] ?? null,
                'status' => $data['status'] ?? 'pending_signature',
                'uploaded_by' => $actor->id,
            ]);

            AuditService::log(
                action: 'CONTRACT_UPLOADED',
                tableName: 'contracts',
                recordId: $contract->id,
                newValues: [
                    'user_id' => $employee->id,
                    'version' => $nextVersion,
                    'file_hash' => $fileHash,
                    'effective_date' => $contract->effective_date?->toDateString(),
                    'expiry_date' => $contract->expiry_date?->toDateString(),
                    'status' => $contract->status,
                ],
                logType: 'success'
            );

            // Notify employee + manager
            Notification::create([
                'user_id' => $employee->id,
                'type' => 'CONTRACT_UPLOADED',
                'title' => 'New Contract Uploaded',
                'message' => "A new employment contract (v{$nextVersion}) has been uploaded for you. Effective: "
                             . $contract->effective_date?->format('d M Y'),
                'reference_id' => $contract->id,
                'reference_type' => Contract::class,
            ]);

            if ($employee->manager) {
                Notification::create([
                    'user_id' => $employee->manager->id,
                    'type' => 'CONTRACT_UPLOADED_MANAGER',
                    'title' => 'Team Contract Uploaded',
                    'message' => "Contract v{$nextVersion} uploaded for {$employee->full_name}.",
                    'reference_id' => $contract->id,
                    'reference_type' => Contract::class,
                ]);
            }

            return $contract;
        });
    }

    /**
     * Renew a contract by creating a new version.
     */
    public function renewContract(Contract $oldContract, array $data, User $actor): Contract
    {
        return DB::transaction(function () use ($oldContract, $data, $actor) {
            $employee = $oldContract->user;

            $lastVersion = Contract::where('user_id', $employee->id)->max('version') ?? $oldContract->version;
            $nextVersion = $lastVersion + 1;

            $new = Contract::create([
                'user_id' => $employee->id,
                'parent_contract_id' => $oldContract->id,
                'version' => $nextVersion,
                'file_path' => $oldContract->file_path, // will be replaced when admin uploads
                'file_hash' => $oldContract->file_hash,
                'effective_date' => $data['effective_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'salary_annual' => $data['salary_annual'] ?? $oldContract->salary_annual,
                'notice_period_days' => $data['notice_period_days'] ?? $oldContract->notice_period_days,
                'position' => $data['position'] ?? $oldContract->position,
                'contract_type' => $data['contract_type'] ?? $oldContract->contract_type,
                'status' => 'pending_signature',
                'uploaded_by' => $actor->id,
            ]);

            // Supersede old
            $oldContract->status = 'renewed';
            $oldContract->renewed_at = now();
            $oldContract->save();

            AuditService::log(
                action: 'CONTRACT_RENEWED',
                tableName: 'contracts',
                recordId: $new->id,
                oldValues: ['parent_status' => 'active', 'parent_version' => $oldContract->version],
                newValues: [
                    'new_version' => $nextVersion,
                    'effective_date' => $new->effective_date?->toDateString(),
                    'expiry_date' => $new->expiry_date?->toDateString(),
                    'salary_annual' => $new->salary_annual,
                ],
                logType: 'success'
            );

            Notification::create([
                'user_id' => $employee->id,
                'type' => 'CONTRACT_RENEWED',
                'title' => 'Contract Renewed',
                'message' => "Your contract has been renewed (v{$nextVersion}). Effective: "
                             . $new->effective_date?->format('d M Y'),
                'reference_id' => $new->id,
                'reference_type' => Contract::class,
            ]);

            if ($employee->manager) {
                Notification::create([
                    'user_id' => $employee->manager->id,
                    'type' => 'CONTRACT_RENEWED_MANAGER',
                    'title' => 'Team Contract Renewed',
                    'message' => "{$employee->full_name}'s contract was renewed to v{$nextVersion}.",
                    'reference_id' => $new->id,
                    'reference_type' => Contract::class,
                ]);
            }

            return $new;
        });
    }

    /**
     * Update contract status (active/terminated/expired).
     */
    public function updateStatus(Contract $contract, string $newStatus, array $data, User $actor): Contract
    {
        return DB::transaction(function () use ($contract, $newStatus, $data, $actor) {
            $oldStatus = $contract->status;

            $contract->status = $newStatus;

            if ($newStatus === 'terminated') {
                $contract->termination_reason = $data['reason'] ?? null;
                $contract->terminated_at = now();
            }

            $contract->save();

            AuditService::log(
                action: 'CONTRACT_STATUS_UPDATED',
                tableName: 'contracts',
                recordId: $contract->id,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => $newStatus,
                    'reason' => $data['reason'] ?? null,
                    'effective_date' => $data['effective_date'] ?? null,
                ],
                logType: $newStatus === 'terminated' ? 'warning' : 'success'
            );

            Notification::create([
                'user_id' => $contract->user_id,
                'type' => 'CONTRACT_STATUS_CHANGED',
                'title' => 'Contract Status Updated',
                'message' => "Your contract status is now: {$newStatus}.",
                'reference_id' => $contract->id,
                'reference_type' => Contract::class,
            ]);

            return $contract->fresh();
        });
    }

    /**
     * Mark contract as signed by employee.
     */
    public function signContract(Contract $contract, UploadedFile $file, User $actor): Contract
    {
        return DB::transaction(function () use ($contract, $file, $actor) {
            $filename = 'contract-signed-' . $contract->id . '-' . time()
                . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('contracts/signed', $filename, 'public');
            $hash = hash_file('sha256', $file->getRealPath());

            $contract->signed_file_path = $path;
            $contract->signed_by_employee = true;
            $contract->status = 'active';
            $contract->save();

            AuditService::log(
                action: 'CONTRACT_SIGNED',
                tableName: 'contracts',
                recordId: $contract->id,
                oldValues: ['status' => 'pending_signature', 'signed_by_employee' => false],
                newValues: [
                    'status' => 'active',
                    'signed_by_employee' => true,
                    'signed_file_hash' => $hash,
                ],
                logType: 'success'
            );

            Notification::create([
                'user_id' => $contract->user_id,
                'type' => 'CONTRACT_ACTIVATED',
                'title' => 'Contract Activated',
                'message' => "Your contract is now active.",
                'reference_id' => $contract->id,
                'reference_type' => Contract::class,
            ]);

            return $contract->fresh();
        });
    }

    /**
     * Send expiry alerts for contracts approaching expiry.
     * Called by scheduled job.
     */
    public function sendExpiryAlerts(): array
    {
        $result = ['30' => 0, '14' => 0, '7' => 0];

        foreach ([30, 14, 7] as $days) {
            $contracts = Contract::active()
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', now()->toDateString())
                ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString())
                ->get();

            foreach ($contracts as $contract) {
                if ($contract->hasAlertBeenSent((string) $days)) {
                    continue;
                }

                $remaining = $contract->daysUntilExpiry();
                if ($remaining === null || $remaining > $days) continue;

                $this->sendExpiryAlert($contract, $days, $remaining);
                $contract->markAlertSent((string) $days);
                $result[(string) $days]++;
            }
        }

        // Also auto-expire stale active contracts
        $this->autoExpireContracts();

        return $result;
    }

    protected function sendExpiryAlert(Contract $contract, int $level, int $remaining): void
    {
        $employee = $contract->user;

        $title = "Contract Expiry Alert — {$remaining} Days";
        $message = "Contract v{$contract->version} for {$employee->full_name} expires on "
                 . $contract->expiry_date->format('d M Y') . " ({$remaining} days remaining).";

        // HR/Admins
        $hrUsers = User::whereIn('role', ['admin', 'super_admin'])->where('is_active', true)->get();
        foreach ($hrUsers as $hr) {
            Notification::create([
                'user_id' => $hr->id,
                'type' => "CONTRACT_EXPIRY_L{$level}_HR",
                'title' => $title,
                'message' => $message,
                'reference_id' => $contract->id,
                'reference_type' => Contract::class,
            ]);
        }

        // Manager
        if ($employee->manager) {
            Notification::create([
                'user_id' => $employee->manager->id,
                'type' => "CONTRACT_EXPIRY_L{$level}_MANAGER",
                'title' => $title,
                'message' => $message,
                'reference_id' => $contract->id,
                'reference_type' => Contract::class,
            ]);
        }

        // Employee
        Notification::create([
            'user_id' => $employee->id,
            'type' => "CONTRACT_EXPIRY_L{$level}_EMPLOYEE",
            'title' => $title,
            'message' => $message,
            'reference_id' => $contract->id,
            'reference_type' => Contract::class,
        ]);

        AuditService::log(
            action: "CONTRACT_EXPIRY_ALERT_L{$level}",
            tableName: 'contracts',
            recordId: $contract->id,
            newValues: [
                'alert_level' => $level,
                'days_remaining' => $remaining,
                'expiry_date' => $contract->expiry_date->toDateString(),
            ],
            logType: 'warning'
        );
    }

    /**
     * Auto-expire active contracts whose expiry_date has passed.
     */
    protected function autoExpireContracts(): int
    {
        $stale = Contract::active()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->get();

        $count = 0;
        foreach ($stale as $contract) {
            $contract->status = 'expired';
            $contract->save();

            AuditService::log(
                action: 'CONTRACT_AUTO_EXPIRED',
                tableName: 'contracts',
                recordId: $contract->id,
                oldValues: ['status' => 'active'],
                newValues: [
                    'status' => 'expired',
                    'expiry_date' => $contract->expiry_date?->toDateString(),
                ],
                logType: 'warning'
            );

            $count++;
        }

        return $count;
    }
}