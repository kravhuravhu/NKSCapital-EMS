<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveCalendar;
use App\Models\LeaveConfig;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveProofService
{
    /**
     * Send reminders for all leave requests pending proof that are past due for a reminder.
     * Called by scheduled command.
     */
    public function sendDueReminders(): array
    {
        $sent = ['employee' => 0, 'manager' => 0, 'warnings' => 0, 'conversions' => 0];

        // Only 8am–5pm working window
        $now = now();
        if (!$this->isWithinWorkingWindow($now)) {
            return $sent;
        }

        $pending = LeaveRequest::pendingProof()
            ->whereNotNull('proof_upload_deadline')
            ->whereNull('proof_uploaded_at')
            ->with(['user.manager'])
            ->get();

        foreach ($pending as $request) {
            $config = LeaveConfig::where('leave_type', $request->leave_type)->first();
            if (!$config) {
                continue;
            }

            // Check deadline passed → convert to unpaid
            if ($now->greaterThanOrEqualTo($request->proof_upload_deadline)) {
                $this->convertToUnpaid($request, $config);
                $sent['conversions']++;
                continue;
            }

            // Check reminder frequency
            $hoursSinceLast = $request->last_proof_reminder_at
                ? $request->last_proof_reminder_at->diffInHours($now)
                : PHP_INT_MAX;

            if ($hoursSinceLast < $config->notification_frequency_hours) {
                continue;
            }

            // Determine if this is a warning (day 2 or 3)
            $isWarning = $this->isWarningPeriod($request);
            $sent[$isWarning ? 'warnings' : 'employee']++;

            $this->sendReminderNotifications($request, $config, $isWarning);

            // Update counters
            $request->proof_reminder_count = $request->proof_reminder_count + 1;
            $request->last_proof_reminder_at = $now;
            $request->save();
        }

        return $sent;
    }

    /**
     * Convert a pending-proof leave to unpaid leave.
     * Deducts from unpaid balance (infinite). If config says so, fall back to annual.
     */
    public function convertToUnpaid(LeaveRequest $request, LeaveConfig $config): void
    {
        DB::transaction(function () use ($request, $config) {
            $user = $request->user;
            $days = (float) $request->days_taken;

            // Restore original balance (sick) — we didn't deduct yet? Actually we did deduct on approval.
            // The original approval already deducted from the original leave type. So we need to:
            // 1. Refund original balance
            // 2. Deduct from unpaid

            $originalType = $request->original_leave_type ?? $request->leave_type;

            // Refund original
            $beforeRefund = $user->getLeaveBalance($originalType);
            $user->addLeaveBalance($originalType, $days);
            LeaveBalance::create([
                'user_id' => $user->id,
                'leave_type' => $originalType,
                'balance_before' => $beforeRefund,
                'balance_after' => $beforeRefund + $days,
                'adjustment_reason' => 'Refund — sick leave converted to unpaid (no proof)',
                'reference_id' => $request->id,
                'reference_type' => 'unpaid_conversion',
                'related_leave_request_id' => $request->id,
                'adjusted_by' => null,
                'adjusted_at' => now(),
            ]);

            // Deduct from unpaid (or fallback)
            $unpaidConfig = LeaveConfig::where('leave_type', 'unpaid')->first();
            $isInfiniteUnpaid = $unpaidConfig && $unpaidConfig->isInfinite();

            if ($isInfiniteUnpaid) {
                // Just log; do not reduce balance
                LeaveBalance::create([
                    'user_id' => $user->id,
                    'leave_type' => 'unpaid',
                    'balance_before' => $user->getLeaveBalance('unpaid'),
                    'balance_after' => $user->getLeaveBalance('unpaid'),
                    'adjustment_reason' => 'Unpaid leave — infinite balance (no proof uploaded for sick leave)',
                    'reference_id' => $request->id,
                    'reference_type' => 'unpaid_conversion',
                    'related_leave_request_id' => $request->id,
                    'adjusted_by' => null,
                    'adjusted_at' => now(),
                ]);
            } else {
                $unpaidBefore = $user->getLeaveBalance('unpaid');
                if ($unpaidBefore >= $days) {
                    $user->deductLeaveBalance('unpaid', $days);
                    LeaveBalance::create([
                        'user_id' => $user->id,
                        'leave_type' => 'unpaid',
                        'balance_before' => $unpaidBefore,
                        'balance_after' => $unpaidBefore - $days,
                        'adjustment_reason' => 'Unpaid leave deducted (no proof uploaded)',
                        'reference_id' => $request->id,
                        'reference_type' => 'unpaid_conversion',
                        'related_leave_request_id' => $request->id,
                        'adjusted_by' => null,
                        'adjusted_at' => now(),
                    ]);
                } else {
                    // Fallback to annual
                    $shortfall = $days - $unpaidBefore;
                    $user->deductLeaveBalance('unpaid', $unpaidBefore);
                    $user->deductLeaveBalance('annual', $shortfall);

                    LeaveBalance::create([
                        'user_id' => $user->id,
                        'leave_type' => 'annual',
                        'balance_before' => $user->getLeaveBalance('annual') + $shortfall,
                        'balance_after' => $user->getLeaveBalance('annual'),
                        'adjustment_reason' => 'Fallback from unpaid (no proof uploaded for sick leave)',
                        'reference_id' => $request->id,
                        'reference_type' => 'annual_fallback',
                        'is_fallback' => true,
                        'related_leave_request_id' => $request->id,
                        'adjusted_by' => null,
                        'adjusted_at' => now(),
                    ]);
                }
            }

            // Update leave request
            $request->original_leave_type = $originalType;
            $request->leave_type = 'unpaid';
            $request->status = 'converted_to_unpaid';
            $request->converted_to_unpaid_at = now();
            $request->save();

            // Update calendar entries
            LeaveCalendar::where('leave_request_id', $request->id)
                ->update([
                    'leave_type' => 'unpaid',
                    'is_unpaid_conversion' => true,
                    'is_pending_proof' => false,
                ]);

            // Notify employee + manager
            $this->notifyConversion($request, $user, $days);
        });
    }

    /**
     * Mark a leave request as proof uploaded and move it to awaiting manager review.
     */
    public function markProofUploaded(LeaveRequest $request, string $path, string $hash): void
    {
        DB::transaction(function () use ($request, $path, $hash) {
            $request->attachment_path = $path;
            $request->attachment_hash = $hash;
            $request->proof_uploaded_at = now();
            // Status remains 'approved_pending_proof' until manager reviews proof
            $request->save();

            // Notify manager for review
            $manager = $request->user->manager;
            if ($manager) {
                Notification::create([
                    'user_id' => $manager->id,
                    'type' => 'LEAVE_PROOF_UPLOADED',
                    'title' => 'Leave Proof Uploaded',
                    'message' => "{$request->user->full_name} has uploaded proof for their {$request->leave_type} leave ({$request->days_taken} days). Please review and finalize.",
                    'reference_id' => $request->id,
                    'reference_type' => LeaveRequest::class,
                ]);
            }
        });
    }

    /**
     * Manager approves the proof → status becomes 'approved' and reminders stop.
     */
    public function approveProof(LeaveRequest $request, User $approver): void
    {
        DB::transaction(function () use ($request, $approver) {
            $request->status = 'approved';
            $request->proof_approved_by = $approver->id;
            $request->proof_approved_at = now();
            $request->save();

            // Update calendar
            LeaveCalendar::where('leave_request_id', $request->id)
                ->update(['is_pending_proof' => false, 'is_approved' => true]);

            Notification::create([
                'user_id' => $request->user_id,
                'type' => 'LEAVE_PROOF_APPROVED',
                'title' => 'Leave Proof Approved',
                'message' => "Your proof for {$request->leave_type} leave has been approved. Leave status is now fully approved.",
                'reference_id' => $request->id,
                'reference_type' => LeaveRequest::class,
            ]);
        });
    }

    // ------------------------------------------------------------
    // INTERNAL HELPERS
    // ------------------------------------------------------------

    protected function isWithinWorkingWindow(Carbon $now): bool
    {
        if ($now->isWeekend()) {
            return false;
        }
        $hour = (int) $now->format('G');
        return $hour >= 8 && $hour < 17;
    }

    protected function isWarningPeriod(LeaveRequest $request): bool
    {
        if (!$request->proof_upload_deadline) {
            return false;
        }
        $hoursLeft = now()->diffInHours($request->proof_upload_deadline, false);
        // Warning if < 24h to deadline OR deadline is today/tomorrow
        return $hoursLeft <= 24;
    }

    protected function sendReminderNotifications(LeaveRequest $request, LeaveConfig $config, bool $isWarning): void
    {
        $user = $request->user;
        $manager = $user->manager;
        $days = (float) $request->days_taken;

        $title = $isWarning ? 'URGENT: Leave Proof Required' : 'Reminder: Leave Proof Required';
        $message = $isWarning
            ? "FINAL WARNING: Your {$request->leave_type} leave ({$days} days) will be converted to UNPAID leave by {$request->proof_upload_deadline->format('d M Y H:i')} if proof is not uploaded."
            : "Please upload proof for your {$request->leave_type} leave ({$days} days). Deadline: {$request->proof_upload_deadline->format('d M Y H:i')}.";

        Notification::create([
            'user_id' => $user->id,
            'type' => $isWarning ? 'LEAVE_PROOF_WARNING' : 'LEAVE_PROOF_REMINDER',
            'title' => $title,
            'message' => $message,
            'reference_id' => $request->id,
            'reference_type' => LeaveRequest::class,
        ]);

        if ($manager) {
            Notification::create([
                'user_id' => $manager->id,
                'type' => $isWarning ? 'LEAVE_PROOF_WARNING_MANAGER' : 'LEAVE_PROOF_REMINDER_MANAGER',
                'title' => "Team Member Leave Proof — {$user->full_name}",
                'message' => "{$user->full_name}'s {$request->leave_type} leave ({$days} days) is missing proof. Deadline: {$request->proof_upload_deadline->format('d M Y H:i')}.",
                'reference_id' => $request->id,
                'reference_type' => LeaveRequest::class,
            ]);
        }
    }

    protected function notifyConversion(LeaveRequest $request, User $user, float $days): void
    {
        Notification::create([
            'user_id' => $user->id,
            'type' => 'LEAVE_CONVERTED_TO_UNPAID',
            'title' => 'Leave Converted to Unpaid',
            'message' => "Your {$days}-day sick leave has been converted to unpaid leave because proof was not uploaded by the deadline.",
            'reference_id' => $request->id,
            'reference_type' => LeaveRequest::class,
        ]);

        if ($user->manager) {
            Notification::create([
                'user_id' => $user->manager->id,
                'type' => 'LEAVE_CONVERTED_TO_UNPAID_MANAGER',
                'title' => 'Leave Converted to Unpaid',
                'message' => "{$user->full_name}'s {$days}-day sick leave was converted to unpaid leave (no proof uploaded).",
                'reference_id' => $request->id,
                'reference_type' => LeaveRequest::class,
            ]);
        }
    }
}