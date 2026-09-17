<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Notification;
use App\Models\Requisition;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecruitmentService
{
    // ============================================================
    // REQUISITION
    // ============================================================

    public function createRequisition(array $data, User $actor): Requisition
    {
        return DB::transaction(function () use ($data, $actor) {
            $code = $data['requisition_code']
                ?? $this->generateRequisitionCode($data['department'] ?? 'GEN');

            $requisition = Requisition::create([
                'requisition_code' => $code,
                'title' => $data['title'],
                'department' => $data['department'],
                'employment_type' => $data['employment_type'],
                'employee_type_target' => $data['employee_type_target'] ?? null,
                'experience_level' => $data['experience_level'] ?? null,
                'priority' => $data['priority'] ?? 'medium',
                'required_skills' => $data['required_skills'] ?? [],
                'salary_range_min' => $data['salary_range_min'] ?? null,
                'salary_range_max' => $data['salary_range_max'] ?? null,
                'salary_currency' => $data['salary_currency'] ?? 'ZAR',
                'reason_for_hire' => $data['reason_for_hire'] ?? null,
                'status' => 'pending_approval',
                'hiring_manager_id' => $data['hiring_manager_id'] ?? $actor->id,
                'target_start_date' => $data['target_start_date'] ?? null,
                'created_by' => $actor->id,
            ]);

            AuditService::log(
                action: 'REQUISITION_CREATED',
                tableName: 'requisitions',
                recordId: $requisition->id,
                newValues: $requisition->toArray(),
                logType: 'success'
            );

            // Notify directors for approval
            $directors = User::where('role', 'director')->where('is_active', true)->get();
            foreach ($directors as $d) {
                Notification::create([
                    'user_id' => $d->id,
                    'type' => 'REQUISITION_PENDING_APPROVAL',
                    'title' => 'New Requisition Pending Approval',
                    'message' => "Requisition {$requisition->requisition_code} ({$requisition->title}) needs your approval.",
                    'reference_id' => $requisition->id,
                    'reference_type' => Requisition::class,
                ]);
            }

            return $requisition;
        });
    }

    public function approveRequisition(Requisition $requisition, User $approver, array $data = []): Requisition
    {
        return DB::transaction(function () use ($requisition, $approver, $data) {
            if (!$requisition->canApprove()) {
                throw new \RuntimeException("Requisition is not pending approval (status: {$requisition->status}).");
            }

            $requisition->status = 'open';
            $requisition->approved_by = $approver->id;
            $requisition->approval_date = now();
            $requisition->save();

            AuditService::log(
                action: 'REQUISITION_APPROVED',
                tableName: 'requisitions',
                recordId: $requisition->id,
                oldValues: ['status' => 'pending_approval'],
                newValues: ['status' => 'open', 'approver_id' => $approver->id],
                logType: 'success'
            );

            Notification::create([
                'user_id' => $requisition->created_by,
                'type' => 'REQUISITION_APPROVED',
                'title' => 'Requisition Approved',
                'message' => "Your requisition {$requisition->requisition_code} was approved and is now open.",
                'reference_id' => $requisition->id,
                'reference_type' => Requisition::class,
            ]);

            return $requisition->fresh();
        });
    }

    // ============================================================
    // CANDIDATE
    // ============================================================

    public function addCandidate(Requisition $requisition, array $data, User $actor, ?string $resumePath = null, ?string $resumeHash = null): Candidate
    {
        return DB::transaction(function () use ($requisition, $data, $actor, $resumePath, $resumeHash) {
            $candidate = Candidate::create([
                'requisition_id' => $requisition->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'years_experience' => $data['years_experience'] ?? 0,
                'expected_salary' => $data['expected_salary'] ?? null,
                'skills' => $data['skills'] ?? [],
                'resume_path' => $resumePath,
                'resume_hash' => $resumeHash,
                'source' => $data['source'] ?? 'manual',
                'linkedin_url' => $data['linkedin_url'] ?? null,
                'tags' => $data['tags'] ?? [],
                'notes' => $data['notes'] ?? null,
                'current_stage' => 'applied',
                'applied_date' => now()->toDateString(),
            ]);

            AuditService::log(
                action: 'CANDIDATE_ADDED',
                tableName: 'candidates',
                recordId: $candidate->id,
                newValues: [
                    'requisition_id' => $requisition->id,
                    'name' => $candidate->full_name,
                    'email' => $candidate->email,
                    'source' => $candidate->source,
                ],
                logType: 'success'
            );

            return $candidate;
        });
    }

    public function advanceStage(Candidate $candidate, string $newStage, User $actor, ?string $notes = null): Candidate
    {
        return DB::transaction(function () use ($candidate, $newStage, $actor, $notes) {
            $oldStage = $candidate->current_stage;

            $candidate->current_stage = $newStage;
            if ($notes) {
                $candidate->notes = trim(($candidate->notes ?? '') . "\n[" . now()->toDateString() . "] Stage: {$notes}");
            }
            $candidate->save();

            AuditService::log(
                action: 'CANDIDATE_STAGE_CHANGED',
                tableName: 'candidates',
                recordId: $candidate->id,
                oldValues: ['current_stage' => $oldStage],
                newValues: ['current_stage' => $newStage, 'notes' => $notes],
                logType: 'success'
            );

            return $candidate->fresh();
        });
    }

    public function rejectCandidate(Candidate $candidate, string $reason, User $actor): Candidate
    {
        return DB::transaction(function () use ($candidate, $reason, $actor) {
            $candidate->current_stage = 'rejected';
            $candidate->rejection_reason = $reason;
            $candidate->rejected_by = $actor->id;
            $candidate->rejected_at = now();
            $candidate->save();

            AuditService::log(
                action: 'CANDIDATE_REJECTED',
                tableName: 'candidates',
                recordId: $candidate->id,
                oldValues: ['current_stage' => 'any'],
                newValues: ['current_stage' => 'rejected', 'reason' => $reason],
                logType: 'warning'
            );

            return $candidate->fresh();
        });
    }

    // ============================================================
    // INTERVIEW
    // ============================================================

    public function scheduleInterview(Candidate $candidate, User $interviewer, array $data, User $actor): Interview
    {
        return DB::transaction(function () use ($candidate, $interviewer, $data, $actor) {
            // Reject if candidate not eligible
            if ($candidate->isRejected() || $candidate->isHired()) {
                throw new \RuntimeException("Cannot schedule interview for {$candidate->current_stage} candidate.");
            }

            $interview = Interview::create([
                'candidate_id' => $candidate->id,
                'interviewer_id' => $interviewer->id,
                'scheduled_at' => Carbon::parse($data['scheduled_at']),
                'duration_minutes' => $data['duration_minutes'] ?? 60,
                'interview_type' => $data['interview_type'] ?? 'video',
                'round' => $data['round'] ?? 1,
                'location_or_link' => $data['location_or_link'] ?? null,
                'meeting_link' => $data['meeting_link'] ?? null,
                'status' => 'scheduled',
            ]);

            // Move candidate to interview stage if not already
            if (in_array($candidate->current_stage, ['applied', 'screening'])) {
                $candidate->current_stage = 'interview';
                $candidate->save();
            }

            AuditService::log(
                action: 'INTERVIEW_SCHEDULED',
                tableName: 'interviews',
                recordId: $interview->id,
                newValues: [
                    'candidate_id' => $candidate->id,
                    'interviewer_id' => $interviewer->id,
                    'scheduled_at' => $interview->scheduled_at->toIso8601String(),
                    'interview_type' => $interview->interview_type,
                    'round' => $interview->round,
                ],
                logType: 'success'
            );

            Notification::create([
                'user_id' => $interviewer->id,
                'type' => 'INTERVIEW_ASSIGNED',
                'title' => 'Interview Assigned',
                'message' => "You are scheduled to interview {$candidate->full_name} on " . $interview->scheduled_at->format('d M Y H:i'),
                'reference_id' => $interview->id,
                'reference_type' => Interview::class,
            ]);

            return $interview;
        });
    }

    public function submitFeedback(Interview $interview, User $interviewer, array $data): Interview
    {
        return DB::transaction(function () use ($interview, $interviewer, $data) {
            if ($interview->interviewer_id !== $interviewer->id) {
                throw new \RuntimeException('You are not the assigned interviewer.');
            }

            if (!$interview->canSubmitFeedback()) {
                throw new \RuntimeException('Feedback has already been submitted or interview is not completed.');
            }

            $interview->feedback_rating = $data['rating'];
            $interview->feedback_notes = $data['notes'] ?? null;
            $interview->recommendation = $data['recommendation'];
            $interview->status = 'completed';
            $interview->completed_at = now();
            $interview->save();

            AuditService::log(
                action: 'INTERVIEW_FEEDBACK_SUBMITTED',
                tableName: 'interviews',
                recordId: $interview->id,
                newValues: [
                    'rating' => $interview->feedback_rating,
                    'recommendation' => $interview->recommendation,
                ],
                logType: 'success'
            );

            // Notify recruiter (requisition creator)
            $recruiter = $interview->candidate->requisition->createdBy;
            if ($recruiter) {
                Notification::create([
                    'user_id' => $recruiter->id,
                    'type' => 'INTERVIEW_FEEDBACK_SUBMITTED',
                    'title' => 'Interview Feedback Submitted',
                    'message' => "Feedback for {$interview->candidate->full_name} submitted by {$interviewer->full_name}. Recommendation: {$interview->recommendation}",
                    'reference_id' => $interview->id,
                    'reference_type' => Interview::class,
                ]);
            }

            return $interview->fresh();
        });
    }

    public function rescheduleInterview(Interview $interview, array $data, User $actor): Interview
    {
        return DB::transaction(function () use ($interview, $data, $actor) {
            if (!$interview->canReschedule()) {
                throw new \RuntimeException("Cannot reschedule interview in status {$interview->status}.");
            }

            $oldScheduled = $interview->scheduled_at;

            $interview->scheduled_at = Carbon::parse($data['scheduled_at']);
            if (isset($data['duration_minutes'])) {
                $interview->duration_minutes = $data['duration_minutes'];
            }
            if (isset($data['location_or_link'])) {
                $interview->location_or_link = $data['location_or_link'];
            }
            if (isset($data['meeting_link'])) {
                $interview->meeting_link = $data['meeting_link'];
            }
            $interview->status = 'rescheduled';
            $interview->reschedule_count = $interview->reschedule_count + 1;
            $interview->save();

            AuditService::log(
                action: 'INTERVIEW_RESCHEDULED',
                tableName: 'interviews',
                recordId: $interview->id,
                oldValues: ['scheduled_at' => $oldScheduled->toIso8601String()],
                newValues: [
                    'scheduled_at' => $interview->scheduled_at->toIso8601String(),
                    'reschedule_count' => $interview->reschedule_count,
                ],
                logType: 'warning'
            );

            Notification::create([
                'user_id' => $interview->interviewer_id,
                'type' => 'INTERVIEW_RESCHEDULED',
                'title' => 'Interview Rescheduled',
                'message' => "Interview with {$interview->candidate->full_name} moved to " . $interview->scheduled_at->format('d M Y H:i'),
                'reference_id' => $interview->id,
                'reference_type' => Interview::class,
            ]);

            return $interview->fresh();
        });
    }

    public function cancelInterview(Interview $interview, string $reason, User $actor): Interview
    {
        return DB::transaction(function () use ($interview, $reason, $actor) {
            if (!$interview->canCancel()) {
                throw new \RuntimeException("Cannot cancel interview in status {$interview->status}.");
            }

            $interview->status = 'cancelled';
            $interview->cancel_reason = $reason;
            $interview->cancelled_by = $actor->id;
            $interview->cancelled_at = now();
            $interview->save();

            AuditService::log(
                action: 'INTERVIEW_CANCELLED',
                tableName: 'interviews',
                recordId: $interview->id,
                newValues: [
                    'reason' => $reason,
                    'cancelled_by' => $actor->id,
                ],
                logType: 'warning'
            );

            Notification::create([
                'user_id' => $interview->interviewer_id,
                'type' => 'INTERVIEW_CANCELLED',
                'title' => 'Interview Cancelled',
                'message' => "Interview with {$interview->candidate->full_name} was cancelled. Reason: {$reason}",
                'reference_id' => $interview->id,
                'reference_type' => Interview::class,
            ]);

            return $interview->fresh();
        });
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function generateRequisitionCode(string $department): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $department), 0, 3)) ?: 'GEN';
        $year = now()->format('Y');
        $count = Requisition::whereYear('created_at', $year)->count() + 1;
        return sprintf('%s-%s-%04d', $prefix, $year, $count);
    }
}