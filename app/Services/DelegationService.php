<?php

namespace App\Services;

use App\Models\ApprovalDelegation;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DelegationService
{
    /**
     * Create a delegation.
     */
    public function createDelegation(array $data, User $actor): ApprovalDelegation
    {
        return DB::transaction(function () use ($data, $actor) {
            $original = User::findOrFail($data['original_approver_id']);
            $delegate = User::findOrFail($data['delegate_id']);

            if ($original->id === $delegate->id) {
                throw new \RuntimeException('Cannot delegate to yourself.');
            }

            if ($delegate->role === 'employee') {
                throw new \RuntimeException('Delegate must have manager, director, or admin role.');
            }

            // Check for overlapping active delegations
            $overlap = ApprovalDelegation::where('original_approver_id', $original->id)
                ->where('is_active', true)
                ->where(function ($q) use ($data) {
                    $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                      ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                      ->orWhere(function ($qq) use ($data) {
                          $qq->where('start_date', '<=', $data['start_date'])
                             ->where('end_date', '>=', $data['end_date']);
                      });
                })
                ->exists();

            if ($overlap) {
                throw new \RuntimeException('An active delegation already exists for this period.');
            }

            $delegation = ApprovalDelegation::create([
                'original_approver_id' => $original->id,
                'delegate_id' => $delegate->id,
                'delegation_type' => $data['delegation_type'] ?? 'both',
                'title' => $data['title'] ?? "Delegation from {$original->full_name}",
                'delegation_modules' => $data['delegation_modules'] ?? ['all'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'] ?? null,
                'created_by' => $actor->id,
                'is_active' => $data['is_active'] ?? true,
                'auto_activate' => $data['auto_activate'] ?? true,
            ]);

            // Auto-activate if start_date is today or past
            if ($delegation->auto_activate && $delegation->start_date->isPast()) {
                $delegation->activated_at = now();
                $delegation->save();
            }

            AuditService::log(
                action: 'DELEGATION_CREATED',
                tableName: 'approval_delegations',
                recordId: $delegation->id,
                newValues: [
                    'original_approver_id' => $original->id,
                    'delegate_id' => $delegate->id,
                    'start_date' => $delegation->start_date->toDateString(),
                    'end_date' => $delegation->end_date->toDateString(),
                    'type' => $delegation->delegation_type,
                ],
                logType: 'success'
            );

            Notification::create([
                'user_id' => $delegate->id,
                'type' => 'DELEGATION_ASSIGNED',
                'title' => 'New Delegation Assigned',
                'message' => "{$original->full_name} has delegated {$delegation->delegation_type} approvals to you from "
                             . $delegation->start_date->format('d M Y') . " to " . $delegation->end_date->format('d M Y') . ".",
                'reference_id' => $delegation->id,
                'reference_type' => ApprovalDelegation::class,
            ]);

            return $delegation;
        });
    }

    /**
     * Update a delegation.
     */
    public function updateDelegation(ApprovalDelegation $delegation, array $data, User $actor): ApprovalDelegation
    {
        return DB::transaction(function () use ($delegation, $data, $actor) {
            if ($delegation->revoked_at) {
                throw new \RuntimeException('Cannot update a revoked delegation.');
            }

            $old = $delegation->toArray();

            $delegation->fill([
                'delegation_type' => $data['delegation_type'] ?? $delegation->delegation_type,
                'title' => $data['title'] ?? $delegation->title,
                'delegation_modules' => $data['delegation_modules'] ?? $delegation->delegation_modules,
                'start_date' => $data['start_date'] ?? $delegation->start_date,
                'end_date' => $data['end_date'] ?? $delegation->end_date,
                'reason' => $data['reason'] ?? $delegation->reason,
                'is_active' => $data['is_active'] ?? $delegation->is_active,
                'auto_activate' => $data['auto_activate'] ?? $delegation->auto_activate,
            ]);

            // If delegate changes, notify
            if (isset($data['delegate_id']) && $data['delegate_id'] != $delegation->delegate_id) {
                $delegation->delegate_id = $data['delegate_id'];
            }

            $delegation->save();

            AuditService::log(
                action: 'DELEGATION_UPDATED',
                tableName: 'approval_delegations',
                recordId: $delegation->id,
                oldValues: $old,
                newValues: $delegation->fresh()->toArray(),
                logType: 'success'
            );

            return $delegation->fresh();
        });
    }

    /**
     * Activate a delegation.
     */
    public function activateDelegation(ApprovalDelegation $delegation, User $actor): ApprovalDelegation
    {
        return DB::transaction(function () use ($delegation, $actor) {
            if ($delegation->revoked_at) {
                throw new \RuntimeException('Cannot activate a revoked delegation.');
            }
            if ($delegation->is_active && $delegation->activated_at) {
                throw new \RuntimeException('Delegation is already active.');
            }

            $delegation->is_active = true;
            $delegation->activated_at = now();
            $delegation->save();

            AuditService::log(
                action: 'DELEGATION_ACTIVATED',
                tableName: 'approval_delegations',
                recordId: $delegation->id,
                newValues: ['activated_at' => now()->toIso8601String()],
                logType: 'success'
            );

            Notification::create([
                'user_id' => $delegation->delegate_id,
                'type' => 'DELEGATION_ACTIVATED',
                'title' => 'Delegation Activated',
                'message' => "Your delegation from {$delegation->originalApprover->full_name} is now active.",
                'reference_id' => $delegation->id,
                'reference_type' => ApprovalDelegation::class,
            ]);

            return $delegation->fresh();
        });
    }

    /**
     * Revoke a delegation.
     */
    public function revokeDelegation(ApprovalDelegation $delegation, string $reason, User $actor): ApprovalDelegation
    {
        return DB::transaction(function () use ($delegation, $reason, $actor) {
            if ($delegation->revoked_at) {
                throw new \RuntimeException('Delegation is already revoked.');
            }

            $delegation->is_active = false;
            $delegation->revoked_at = now();
            $delegation->revoked_by = $actor->id;
            $delegation->revoke_reason = $reason;
            $delegation->save();

            AuditService::log(
                action: 'DELEGATION_REVOKED',
                tableName: 'approval_delegations',
                recordId: $delegation->id,
                oldValues: ['is_active' => true],
                newValues: [
                    'is_active' => false,
                    'reason' => $reason,
                    'revoked_by' => $actor->id,
                ],
                logType: 'warning'
            );

            Notification::create([
                'user_id' => $delegation->delegate_id,
                'type' => 'DELEGATION_REVOKED',
                'title' => 'Delegation Revoked',
                'message' => "Your delegation from {$delegation->originalApprover->full_name} has been revoked. Reason: {$reason}",
                'reference_id' => $delegation->id,
                'reference_type' => ApprovalDelegation::class,
            ]);

            return $delegation->fresh();
        });
    }

    /**
     * Get the active delegate for a user (if any).
     */
    public function findActiveDelegate(User $approver): ?User
    {
        $delegation = ApprovalDelegation::where('original_approver_id', $approver->id)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->first();

        return $delegation?->delegate;
    }
}