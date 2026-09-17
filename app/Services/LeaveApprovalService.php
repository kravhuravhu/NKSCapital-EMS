<?php

namespace App\Services;

use App\Models\LeaveConfig;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;

class LeaveApprovalService
{
    /**
     * Evaluate a leave application and return the initial status decision.
     *
     * Returns:
     *   [
     *     'status' => 'pending' | 'auto_approved' | 'approved_pending_proof' | 'rejected',
     *     'reason' => string,
     *     'requires_proof' => bool,
     *     'errors' => array
     *   ]
     */
    public function evaluateApplication(
        User $user,
        string $leaveType,
        float $daysTaken,
        bool $hasAttachment = false
    ): array {
        $config = LeaveConfig::where('leave_type', $leaveType)->first();

        if (!$config) {
            return [
                'status' => 'rejected',
                'reason' => "Unknown leave type: {$leaveType}",
                'requires_proof' => false,
                'errors' => ['leave_type' => "Leave type '{$leaveType}' is not configured."],
            ];
        }

        // 1. Service-month requirement
        $minMonths = (float) $config->min_service_months;
        if ($minMonths > 0 && !$user->meetsMinService($minMonths)) {
            $months = $user->getMonthsOfService();
            return [
                'status' => 'rejected',
                'reason' => "You must have at least {$minMonths} months of service for {$leaveType} leave. You currently have {$months} months.",
                'requires_proof' => false,
                'errors' => [
                    'service_months' => [
                        'required' => $minMonths,
                        'current' => $months,
                        'suggestion' => 'You may apply for unpaid leave instead.',
                    ],
                ],
            ];
        }

        // 2. Determine approval path
        $isAutoApprovable = $config->isAutoApprovable($daysTaken);
        $requiresProof = $config->requiresProofFor($daysTaken);

        // Auto-approve path (sick leave 1-2 days only)
        if ($isAutoApprovable) {
            return [
                'status' => 'auto_approved',
                'reason' => 'Auto-approved',
                'requires_proof' => false,
                'errors' => [],
            ];
        }

        // Manual path
        return [
            'status' => 'pending',
            'reason' => 'Awaiting approval',
            'requires_proof' => $requiresProof,
            'errors' => [],
        ];
    }

    /**
     * Determine status after manager approval.
     * If requires_proof → 'approved_pending_proof'
     * Else → 'approved'
     */
    public function resolveApprovedStatus(LeaveRequest $request, LeaveConfig $config): string
    {
        $days = $request->partial_approved_days ?? $request->days_taken;

        if ($config->requiresProofFor((float) $days) && !$request->hasProof()) {
            return 'approved_pending_proof';
        }

        return 'approved';
    }

    /**
     * Compute the proof upload deadline for a leave request.
     */
    public function computeProofDeadline(LeaveRequest $request, LeaveConfig $config): ?Carbon
    {
        if (!$config->requiresProofFor((float) $request->days_taken)) {
            return null;
        }
        return $request->calculateProofDeadline($config->proof_upload_deadline_days);
    }

    /**
     * Check if a leave type should be blocked from timesheet hours.
     */
    public function blocksTimesheet(LeaveCalendar $calendarEntry): bool
    {
        return $calendarEntry->is_approved
            || $calendarEntry->is_pending_proof
            || $calendarEntry->is_unpaid_conversion;
    }

    /**
     * Whether a given leave type is infinite (e.g., unpaid).
     */
    public function isInfinite(string $leaveType): bool
    {
        $config = LeaveConfig::where('leave_type', $leaveType)->first();
        return $config && $config->isInfinite();
    }
}