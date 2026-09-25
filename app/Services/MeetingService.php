<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class MeetingService
{
    /**
     * Schedule a new meeting.
     */
    public function scheduleMeeting(array $data, User $actor): Meeting
    {
        return DB::transaction(function () use ($data, $actor) {
            $secret = bin2hex(random_bytes(16));

            $meeting = Meeting::create([
                'title' => $data['title'],
                'type' => $data['type'] ?? 'general',
                'is_mandatory' => $data['is_mandatory'] ?? false,
                'late_threshold_minutes' => $data['late_threshold_minutes'] ?? 5,
                'excessive_late_threshold_minutes' => $data['excessive_late_threshold_minutes'] ?? 20,
                'department' => $data['department'] ?? $actor->department,
                'description' => $data['description'] ?? null,
                'start_time' => Carbon::parse($data['start_time']),
                'end_time' => Carbon::parse($data['end_time']),
                'location' => $data['location'] ?? null,
                'meeting_link' => $data['meeting_link'] ?? null,
                'qr_code_secret' => $secret,
                'created_by' => $actor->id,
                'status' => 'scheduled',
            ]);

            // Generate QR code image
            if (class_exists(QrCode::class)) {
                $qrContent = route('meeting.checkin.redirect', ['secret' => $secret]);
                $qrImage = QrCode::format('png')->size(400)->generate($qrContent);
                $path = 'meetings/qr/' . $meeting->id . '.png';
                Storage::disk('public')->put($path, $qrImage);
                $meeting->qr_code_path = $path;
                $meeting->save();
            }

            AuditService::log(
                action: 'MEETING_SCHEDULED',
                tableName: 'meetings',
                recordId: $meeting->id,
                newValues: [
                    'title' => $meeting->title,
                    'type' => $meeting->type,
                    'start_time' => $meeting->start_time->toIso8601String(),
                    'end_time' => $meeting->end_time->toIso8601String(),
                    'is_mandatory' => $meeting->is_mandatory,
                ],
                logType: 'success'
            );

            // Notify attendees (department-based)
            $this->notifyAttendees($meeting, $actor);

            return $meeting;
        });
    }

    /**
     * Update a meeting.
     */
    public function updateMeeting(Meeting $meeting, array $data, User $actor): Meeting
    {
        return DB::transaction(function () use ($meeting, $data, $actor) {
            if (!$meeting->canBeUpdated()) {
                throw new \RuntimeException("Cannot update meeting in status {$meeting->status}");
            }

            $old = $meeting->toArray();

            $meeting->fill([
                'title' => $data['title'] ?? $meeting->title,
                'type' => $data['type'] ?? $meeting->type,
                'is_mandatory' => $data['is_mandatory'] ?? $meeting->is_mandatory,
                'late_threshold_minutes' => $data['late_threshold_minutes'] ?? $meeting->late_threshold_minutes,
                'excessive_late_threshold_minutes' => $data['excessive_late_threshold_minutes'] ?? $meeting->excessive_late_threshold_minutes,
                'department' => $data['department'] ?? $meeting->department,
                'description' => $data['description'] ?? $meeting->description,
                'location' => $data['location'] ?? $meeting->location,
                'meeting_link' => $data['meeting_link'] ?? $meeting->meeting_link,
            ]);

            if (isset($data['start_time'])) $meeting->start_time = Carbon::parse($data['start_time']);
            if (isset($data['end_time'])) $meeting->end_time = Carbon::parse($data['end_time']);

            $meeting->save();

            AuditService::log(
                action: 'MEETING_UPDATED',
                tableName: 'meetings',
                recordId: $meeting->id,
                oldValues: $old,
                newValues: $meeting->fresh()->toArray(),
                logType: 'success'
            );

            return $meeting->fresh();
        });
    }

    /**
     * Cancel a meeting.
     */
    public function cancelMeeting(Meeting $meeting, string $reason, User $actor): Meeting
    {
        return DB::transaction(function () use ($meeting, $reason, $actor) {
            if (!$meeting->canBeCancelled()) {
                throw new \RuntimeException("Cannot cancel meeting in status {$meeting->status}");
            }

            $meeting->status = 'cancelled';
            $meeting->cancellation_reason = $reason;
            $meeting->cancelled_by = $actor->id;
            $meeting->cancelled_at = now();
            $meeting->save();

            AuditService::log(
                action: 'MEETING_CANCELLED',
                tableName: 'meetings',
                recordId: $meeting->id,
                oldValues: ['status' => 'scheduled'],
                newValues: ['status' => 'cancelled', 'reason' => $reason],
                logType: 'warning'
            );

            // Notify attendees
            $attendees = $this->resolveAttendees($meeting);
            foreach ($attendees as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'MEETING_CANCELLED',
                    'title' => 'Meeting Cancelled',
                    'message' => "Meeting '{$meeting->title}' has been cancelled. Reason: {$reason}",
                    'reference_id' => $meeting->id,
                    'reference_type' => Meeting::class,
                ]);
            }

            return $meeting->fresh();
        });
    }

    /**
     * Record a check-in.
     */
    public function checkIn(Meeting $meeting, User $user, string $signMethod, ?string $notes = null): MeetingAttendance
    {
        return DB::transaction(function () use ($meeting, $user, $signMethod, $notes) {
            if (!$meeting->canAcceptCheckin()) {
                throw new \RuntimeException('Meeting is not currently accepting check-ins.');
            }

            // Prevent duplicate check-in
            $existing = MeetingAttendance::where('meeting_id', $meeting->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                throw new \RuntimeException('You have already checked in for this meeting.');
            }

            $now = now();
            $minutesLate = 0;
            if ($now->greaterThan($meeting->start_time)) {
                $minutesLate = (int) $meeting->start_time->diffInMinutes($now);
            }

            $lateStatus = $meeting->computeLateStatus($minutesLate);

            $attendance = MeetingAttendance::create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'check_in_time' => $now,
                'is_late' => $lateStatus !== 'on_time',
                'minutes_late' => $minutesLate,
                'late_status' => $lateStatus,
                'sign_method' => $signMethod,
                'notes' => $notes,
            ]);

            AuditService::log(
                action: 'MEETING_CHECKIN',
                tableName: 'meeting_attendance',
                recordId: $attendance->id,
                newValues: [
                    'meeting_id' => $meeting->id,
                    'user_id' => $user->id,
                    'late_status' => $lateStatus,
                    'minutes_late' => $minutesLate,
                    'sign_method' => $signMethod,
                ],
                logType: $lateStatus === 'excessive_late' ? 'warning' : 'success'
            );

            // Escalate excessive late
            if ($lateStatus === 'excessive_late') {
                $this->escalateExcessiveLate($meeting, $user, $attendance);
            }

            return $attendance;
        });
    }

    /**
     * Manager override for attendance.
     */
    public function overrideAttendance(Meeting $meeting, User $targetUser, User $manager, string $reason, bool $excused = false): MeetingAttendance
    {
        return DB::transaction(function () use ($meeting, $targetUser, $manager, $reason, $excused) {
            $attendance = MeetingAttendance::where('meeting_id', $meeting->id)
                ->where('user_id', $targetUser->id)
                ->first();

            if ($attendance) {
                $attendance->update([
                    'sign_method' => 'manual_override',
                    'override_reason' => $reason,
                    'override_by' => $manager->id,
                    'excused' => $excused,
                    'late_status' => $excused ? 'on_time' : $attendance->late_status,
                    'is_late' => $excused ? false : $attendance->is_late,
                ]);
            } else {
                $attendance = MeetingAttendance::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $targetUser->id,
                    'check_in_time' => now(),
                    'is_late' => false,
                    'minutes_late' => 0,
                    'late_status' => 'on_time',
                    'sign_method' => 'manual_override',
                    'override_reason' => $reason,
                    'override_by' => $manager->id,
                    'excused' => $excused,
                ]);
            }

            AuditService::log(
                action: 'MEETING_ATTENDANCE_OVERRIDDEN',
                tableName: 'meeting_attendance',
                recordId: $attendance->id,
                newValues: [
                    'meeting_id' => $meeting->id,
                    'target_user_id' => $targetUser->id,
                    'override_by' => $manager->id,
                    'reason' => $reason,
                    'excused' => $excused,
                ],
                logType: 'warning'
            );

            Notification::create([
                'user_id' => $targetUser->id,
                'type' => 'ATTENDANCE_OVERRIDDEN',
                'title' => 'Attendance Updated',
                'message' => "Your attendance for '{$meeting->title}' has been updated by {$manager->full_name}.",
                'reference_id' => $attendance->id,
                'reference_type' => MeetingAttendance::class,
            ]);

            return $attendance->fresh();
        });
    }

    /**
     * Notify attendees when meeting is scheduled.
     */
    protected function notifyAttendees(Meeting $meeting, User $actor): void
    {
        $attendees = $this->resolveAttendees($meeting);

        foreach ($attendees as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'MEETING_SCHEDULED',
                'title' => 'New Meeting: ' . $meeting->title,
                'message' => "Meeting scheduled for {$meeting->start_time->format('d M Y H:i')}. Location: " . ($meeting->location ?? 'TBC'),
                'reference_id' => $meeting->id,
                'reference_type' => Meeting::class,
            ]);
        }
    }

    /**
     * Resolve attendees from department or fallback to all active users.
     */
    protected function resolveAttendees(Meeting $meeting): \Illuminate\Support\Collection
    {
        if ($meeting->department) {
            return User::where('department', $meeting->department)
                ->where('is_active', true)
                ->get();
        }
        return User::where('is_active', true)->get();
    }

    /**
     * Escalate excessive late attendance to manager.
     */
    protected function escalateExcessiveLate(Meeting $meeting, User $user, MeetingAttendance $attendance): void
    {
        if ($attendance->escalation_level > 0) return;

        $manager = $user->manager;
        $message = "{$user->full_name} was excessively late ({$attendance->minutes_late} min) for '{$meeting->title}'.";

        if ($manager) {
            Notification::create([
                'user_id' => $manager->id,
                'type' => 'EXCESSIVE_LATE_MANAGER',
                'title' => 'Team Member Excessively Late',
                'message' => $message,
                'reference_id' => $attendance->id,
                'reference_type' => MeetingAttendance::class,
            ]);
        }

        $attendance->update([
            'escalation_level' => 1,
            'escalation_sent_at' => now(),
        ]);
    }
}