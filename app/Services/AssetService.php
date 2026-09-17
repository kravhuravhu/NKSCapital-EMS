<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCustodyHistory;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssetService
{
    /**
     * Register a new asset.
     */
    public function registerAsset(array $data, User $actor): Asset
    {
        return DB::transaction(function () use ($data, $actor) {
            $asset = Asset::create([
                'asset_tag' => $data['asset_tag'],
                'serial_number' => $data['serial_number'] ?? null,
                'model' => $data['model'],
                'manufacturer' => $data['manufacturer'] ?? null,
                'purchase_date' => $data['purchase_date'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
                'warranty_expiry' => $data['warranty_expiry'] ?? null,
                'condition' => $data['condition'] ?? 'good',
                'status' => 'available',
                'location' => $data['location'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            AuditService::log(
                action: 'ASSET_REGISTERED',
                tableName: 'assets',
                recordId: $asset->id,
                newValues: $asset->toArray(),
                logType: 'success'
            );

            return $asset;
        });
    }

    /**
     * Loan an asset to an employee.
     */
    public function loanAsset(Asset $asset, User $assignee, array $data, User $actor): AssetCustodyHistory
    {
        return DB::transaction(function () use ($asset, $assignee, $data, $actor) {
            if ($asset->status !== 'available') {
                throw new \RuntimeException("Asset is not available (status: {$asset->status}).");
            }

            $checkoutDate = isset($data['checkout_date'])
                ? Carbon::parse($data['checkout_date'])
                : now();

            $expectedReturn = isset($data['expected_return_date'])
                ? Carbon::parse($data['expected_return_date'])
                : null;

            $history = AssetCustodyHistory::create([
                'asset_id' => $asset->id,
                'assigned_to_id' => $assignee->id,
                'checkout_date' => $checkoutDate,
                'expected_return_date' => $expectedReturn,
                'checkout_condition' => $data['checkout_condition'] ?? $asset->condition,
                'created_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $asset->update([
                'status' => 'loaned',
                'current_assignee_id' => $assignee->id,
                'location' => $data['location'] ?? $asset->location,
                'overdue_notification_level' => 0,
                'last_overdue_notified_at' => null,
            ]);

            AuditService::log(
                action: 'ASSET_LOANED',
                tableName: 'assets',
                recordId: $asset->id,
                oldValues: ['status' => 'available'],
                newValues: [
                    'status' => 'loaned',
                    'assigned_to' => $assignee->id,
                    'checkout_date' => $checkoutDate->toDateString(),
                    'expected_return_date' => $expectedReturn?->toDateString(),
                    'custody_history_id' => $history->id,
                ],
                logType: 'success'
            );

            // Notify assignee
            Notification::create([
                'user_id' => $assignee->id,
                'type' => 'ASSET_LOANED',
                'title' => 'Asset Loaned to You',
                'message' => "Asset {$asset->asset_tag} ({$asset->model}) has been loaned to you. Expected return: " .
                             ($expectedReturn ? $expectedReturn->format('d M Y') : 'N/A'),
                'reference_id' => $asset->id,
                'reference_type' => Asset::class,
            ]);

            return $history;
        });
    }

    /**
     * Return a loaned asset.
     */
    public function returnAsset(Asset $asset, array $data, User $actor): AssetCustodyHistory
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            $activeLoan = $asset->activeLoan()->first();

            if (!$activeLoan) {
                throw new \RuntimeException('No active loan found for this asset.');
            }

            $returnDate = isset($data['actual_return_date'])
                ? Carbon::parse($data['actual_return_date'])
                : now();

            $returnCondition = $data['return_condition'] ?? $asset->condition;
            $needsRepair = in_array($returnCondition, ['damaged', 'poor', 'for_repair']);

            $activeLoan->update([
                'actual_return_date' => $returnDate,
                'return_condition' => $returnCondition,
                'damage_photo' => $data['damage_photo_path'] ?? null,
                'damage_photo_hash' => $data['damage_photo_hash'] ?? null,
                'repair_priority' => $data['repair_priority'] ?? null,
                'notes' => $data['notes'] ?? null,
                'returned_to_location' => $data['returned_to_location'] ?? $asset->location,
            ]);

            $newStatus = $needsRepair ? 'repair_requested' : 'available';

            $asset->update([
                'status' => $newStatus,
                'current_assignee_id' => null,
                'condition' => $returnCondition,
                'location' => $data['returned_to_location'] ?? $asset->location,
                'overdue_notification_level' => 0,
                'last_overdue_notified_at' => null,
            ]);

            AuditService::log(
                action: $needsRepair ? 'ASSET_RETURNED_DAMAGED' : 'ASSET_RETURNED',
                tableName: 'assets',
                recordId: $asset->id,
                oldValues: ['status' => 'loaned'],
                newValues: [
                    'status' => $newStatus,
                    'return_condition' => $returnCondition,
                    'actual_return_date' => $returnDate->toDateString(),
                    'custody_history_id' => $activeLoan->id,
                    'needs_repair' => $needsRepair,
                ],
                logType: 'success'
            );

            return $activeLoan->fresh();
        });
    }

    /**
     * Request repair for an asset.
     */
    public function requestRepair(Asset $asset, array $data, User $actor): Asset
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            $oldStatus = $asset->status;

            $asset->update([
                'status' => 'repair_requested',
                'condition' => 'for_repair',
                'notes' => trim(($asset->notes ?? '') . "\n[" . now()->toDateString() . "] Repair: " . ($data['issue_description'] ?? 'N/A')),
            ]);

            // Attach to current custody record or create a new one
            $activeLoan = $asset->activeLoan()->first();
            if ($activeLoan) {
                $activeLoan->update([
                    'return_condition' => 'for_repair',
                    'repair_priority' => $data['urgency'] ?? 'medium',
                    'damage_photo' => $data['damage_photo_path'] ?? $activeLoan->damage_photo,
                    'damage_photo_hash' => $data['damage_photo_hash'] ?? $activeLoan->damage_photo_hash,
                    'notes' => $data['issue_description'] ?? $activeLoan->notes,
                ]);
            }

            AuditService::log(
                action: 'ASSET_REPAIR_REQUESTED',
                tableName: 'assets',
                recordId: $asset->id,
                oldValues: ['status' => $oldStatus, 'condition' => 'good'],
                newValues: [
                    'status' => 'repair_requested',
                    'condition' => 'for_repair',
                    'urgency' => $data['urgency'] ?? 'medium',
                    'issue_description' => $data['issue_description'] ?? null,
                    'reported_by' => $actor->id,
                ],
                logType: 'warning'
            );

            // Notify managers/admins
            $this->notifyManagersAndAdmins(
                $actor,
                'ASSET_REPAIR_REQUESTED',
                'Asset Repair Requested',
                "Asset {$asset->asset_tag} ({$asset->model}) has a repair request. Urgency: " . ($data['urgency'] ?? 'medium')
            );

            return $asset->fresh();
        });
    }

    /**
     * Flag overdue assets and escalate.
     * Called by scheduled job.
     */
    public function escalateOverdue(): array
    {
        $result = ['level3' => 0, 'level7' => 0, 'level14' => 0];
        $now = now();

        $overdueAssets = Asset::overdue()->get();

        foreach ($overdueAssets as $asset) {
            $days = $asset->daysOverdue();
            $level = (int) $asset->overdue_notification_level;

            if ($days >= 14 && $level < 14) {
                $this->escalateAsset($asset, 14, $days);
                $result['level14']++;
            } elseif ($days >= 7 && $level < 7) {
                $this->escalateAsset($asset, 7, $days);
                $result['level7']++;
            } elseif ($days >= 3 && $level < 3) {
                $this->escalateAsset($asset, 3, $days);
                $result['level3']++;
            }
        }

        return $result;
    }

    /**
     * Escalate a single asset to the given level.
     */
    protected function escalateAsset(Asset $asset, int $level, int $daysOverdue): void
    {
        $assignee = $asset->currentAssignee;
        $manager = $assignee?->manager;

        $title = match ($level) {
            14 => 'URGENT: Asset Overdue 14+ Days — Director Notified',
            7 => 'Asset Overdue 7 Days',
            default => 'Asset Overdue 3 Days',
        };

        $message = "Asset {$asset->asset_tag} ({$asset->model}) is overdue by {$daysOverdue} days. "
                 . "Assigned to: " . ($assignee?->full_name ?? 'N/A');

        // Notify assignee
        if ($assignee) {
            Notification::create([
                'user_id' => $assignee->id,
                'type' => "ASSET_OVERDUE_L{$level}",
                'title' => $title,
                'message' => $message,
                'reference_id' => $asset->id,
                'reference_type' => Asset::class,
            ]);
        }

        // Notify manager
        if ($manager) {
            Notification::create([
                'user_id' => $manager->id,
                'type' => "ASSET_OVERDUE_L{$level}_MANAGER",
                'title' => $title . ' (Team Member)',
                'message' => $message,
                'reference_id' => $asset->id,
                'reference_type' => Asset::class,
            ]);
        }

        // Notify directors at level 14
        if ($level === 14) {
            $directors = User::where('role', 'director')->where('is_active', true)->get();
            foreach ($directors as $director) {
                Notification::create([
                    'user_id' => $director->id,
                    'type' => 'ASSET_OVERDUE_L14_DIRECTOR',
                    'title' => 'Director Alert: Asset Overdue 14+ Days',
                    'message' => $message,
                    'reference_id' => $asset->id,
                    'reference_type' => Asset::class,
                ]);
            }
        }

        $asset->update([
            'overdue_notification_level' => $level,
            'last_overdue_notified_at' => now(),
        ]);

        AuditService::log(
            action: "ASSET_OVERDUE_ESCALATED_L{$level}",
            tableName: 'assets',
            recordId: $asset->id,
            oldValues: ['overdue_notification_level' => $asset->getOriginal('overdue_notification_level')],
            newValues: [
                'overdue_notification_level' => $level,
                'days_overdue' => $daysOverdue,
                'assignee_id' => $assignee?->id,
            ],
            logType: 'warning'
        );
    }

    protected function notifyManagersAndAdmins(User $actor, string $type, string $title, string $message): void
    {
        $recipients = User::whereIn('role', ['manager', 'admin', 'director'])
            ->where('is_active', true)
            ->where('id', '!=', $actor->id)
            ->get();

        foreach ($recipients as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'reference_id' => null,
                'reference_type' => null,
            ]);
        }
    }
}