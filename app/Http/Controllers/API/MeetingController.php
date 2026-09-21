<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\User;
use App\Services\MeetingService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MeetingController extends Controller
{
    protected MeetingService $meetingService;

    public function __construct(MeetingService $meetingService)
    {
        $this->meetingService = $meetingService;
    }

    /**
     * POST /api/v1/meeting/schedule
     */
    public function schedule(Request $request)
    {
        $user = $this->requireRole(['manager', 'director', 'admin', 'super_admin']);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'type' => 'nullable|in:general,team,client,training,review',
            'is_mandatory' => 'nullable|boolean',
            'late_threshold_minutes' => 'nullable|integer|min:1|max:60',
            'excessive_late_threshold_minutes' => 'nullable|integer|min:5|max:180',
            'department' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $meeting = $this->meetingService->scheduleMeeting($request->all(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Meeting scheduled',
            'data' => $meeting,
        ], 201);
    }

    /**
     * GET /api/v1/meeting/list
     */
    public function list(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:scheduled,ongoing,completed,cancelled,postponed',
            'type' => 'nullable|in:general,team,client,training,review',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'scope' => 'nullable|in:my,upcoming,today,past,all',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = Meeting::with([
            'createdBy:id,first_name,last_name',
            'attendance' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            },
        ]);

        // Scope
        $scope = $request->scope ?? 'all';
        if ($scope === 'upcoming') $query->upcoming();
        if ($scope === 'today') $query->today();
        if ($scope === 'past') $query->past();

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('type')) $query->where('type', $request->type);
        if ($request->filled('from')) $query->where('start_time', '>=', $request->from);
        if ($request->filled('to')) $query->where('start_time', '<=', $request->to);

        $meetings = $query->orderBy('start_time', 'desc')->paginate($request->per_page ?? 15);

        // Decorate with user's attendance status
        $meetings->getCollection()->transform(function ($meeting) use ($user) {
            $meeting->my_attendance = $meeting->attendance->first();
            $meeting->has_checked_in = !is_null($meeting->my_attendance);
            return $meeting;
        });

        return response()->json(['status' => 'success', 'data' => $meetings], 200);
    }

    /**
     * GET /api/v1/meeting/qr/{meetingId}
     */
    public function qr($meetingId)
    {
        $user = Auth::user();
        $meeting = Meeting::findOrFail($meetingId);

        if (!$meeting->canAcceptCheckin()) {
            return response()->json(['status' => 'error', 'message' => 'Meeting is not currently accepting check-ins'], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'meeting_id' => $meeting->id,
                'title' => $meeting->title,
                'qr_content' => $meeting->qr_code_secret,
                'qr_url' => $meeting->qr_code_path ? Storage::url($meeting->qr_code_path) : null,
                'checkin_url' => route('meeting.checkin.redirect', ['secret' => $meeting->qr_code_secret]),
            ]
        ], 200);
    }

    /**
     * GET /api/v1/meeting/ical/{meetingId}
     */
    public function ical($meetingId)
    {
        $meeting = Meeting::with('createdBy')->findOrFail($meetingId);

        $ical = $this->buildIcal($meeting);

        return response($ical)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="meeting-' . $meeting->id . '.ics"');
    }

    /**
     * POST /api/v1/meeting/checkin
     */
    public function checkin(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'meeting_id' => 'nullable|exists:meetings,id',
            'qr_secret' => 'nullable|string',
            'manual_code' => 'nullable|string',
            'sign_method' => 'nullable|in:qr_code_scan,manual_code,manual_override,email',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Resolve meeting by id or qr_secret
        if ($request->filled('meeting_id')) {
            $meeting = Meeting::findOrFail($request->meeting_id);
        } elseif ($request->filled('qr_secret')) {
            $meeting = Meeting::where('qr_code_secret', $request->qr_secret)->firstOrFail();
        } else {
            return response()->json(['status' => 'error', 'message' => 'meeting_id or qr_secret is required'], 422);
        }

        try {
            $attendance = $this->meetingService->checkIn(
                $meeting,
                $user,
                $request->sign_method ?? 'qr_code_scan',
                $request->notes
            );
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Checked in successfully',
            'data' => [
                'attendance' => $attendance,
                'meeting' => $meeting->only(['id', 'title', 'start_time', 'end_time']),
                'late_status' => $attendance->late_status,
                'minutes_late' => $attendance->minutes_late,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/meeting/override
     */
    public function override(Request $request)
    {
        $user = $this->requireRole(['manager', 'director', 'admin', 'super_admin']);

        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|exists:meetings,id',
            'user_id' => 'required|exists:users,id',
            'reason' => 'required|string|max:1000',
            'excused' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $meeting = Meeting::findOrFail($request->meeting_id);
        $target = User::findOrFail($request->user_id);

        try {
            $attendance = $this->meetingService->overrideAttendance(
                $meeting,
                $target,
                $user,
                $request->reason,
                $request->boolean('excused', false)
            );
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance overridden',
            'data' => $attendance,
        ], 200);
    }

    /**
     * POST /api/v1/meeting/cancel/{id}
     */
    public function cancel(Request $request, $id)
    {
        $user = $this->requireRole(['manager', 'director', 'admin', 'super_admin']);

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $meeting = Meeting::findOrFail($id);

        try {
            $meeting = $this->meetingService->cancelMeeting($meeting, $request->reason, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Meeting cancelled',
            'data' => $meeting,
        ], 200);
    }

    /**
     * PUT /api/v1/meeting/update/{id}
     */
    public function update(Request $request, $id)
    {
        $user = $this->requireRole(['manager', 'director', 'admin', 'super_admin']);
        $meeting = Meeting::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:general,team,client,training,review',
            'is_mandatory' => 'sometimes|boolean',
            'late_threshold_minutes' => 'sometimes|integer|min:1|max:60',
            'excessive_late_threshold_minutes' => 'sometimes|integer|min:5|max:180',
            'department' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $meeting = $this->meetingService->updateMeeting($meeting, $request->all(), $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Meeting updated',
            'data' => $meeting,
        ], 200);
    }

    /**
     * GET /api/v1/meeting/attendance/{meetingId}
     */
    public function attendance($meetingId)
    {
        $user = $this->requireRole(['manager', 'director', 'admin', 'super_admin', 'recruiter']);
        $meeting = Meeting::with([
            'attendance.user:id,first_name,last_name,employee_number,department,email',
            'attendance.overrideBy:id,first_name,last_name',
        ])->findOrFail($meetingId);

        $stats = [
            'total_checked_in' => $meeting->attendance->count(),
            'on_time' => $meeting->attendance->where('late_status', 'on_time')->count(),
            'late' => $meeting->attendance->where('late_status', 'late')->count(),
            'excessive_late' => $meeting->attendance->where('late_status', 'excessive_late')->count(),
            'excused' => $meeting->attendance->where('excused', true)->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'meeting' => $meeting->only(['id', 'title', 'start_time', 'end_time', 'location', 'status']),
                'stats' => $stats,
                'attendance' => $meeting->attendance,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/meeting/late-report
     */
    public function lateReport(Request $request)
    {
        $this->requireRole(['manager', 'director', 'admin', 'super_admin']);

        $validator = Validator::make($request->all(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'user_id' => 'nullable|exists:users,id',
            'department' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        $query = MeetingAttendance::with([
            'user:id,first_name,last_name,employee_number,department',
            'meeting:id,title,start_time',
        ])->whereBetween('check_in_time', [$from, $to])
          ->whereIn('late_status', ['late', 'excessive_late'])
          ->where('excused', false);

        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('department')) {
            $query->whereHas('user', fn ($q) => $q->where('department', $request->department));
        }

        $records = $query->orderBy('check_in_time', 'desc')->get();

        $stats = [
            'total_late' => $records->where('late_status', 'late')->count(),
            'total_excessive_late' => $records->where('late_status', 'excessive_late')->count(),
            'by_user' => $records->groupBy('user_id')->map(function ($items) {
                $user = $items->first()->user;
                return [
                    'user_id' => $user->id,
                    'name' => $user->full_name,
                    'employee_number' => $user->employee_number,
                    'department' => $user->department,
                    'late_count' => $items->where('late_status', 'late')->count(),
                    'excessive_late_count' => $items->where('late_status', 'excessive_late')->count(),
                    'total_incidents' => $items->count(),
                    'avg_minutes_late' => round($items->avg('minutes_late'), 1),
                ];
            })->values(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'stats' => $stats,
                'records' => $records,
            ]
        ], 200);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function requireRole(array $roles): User
    {
        $user = Auth::user();
        if (!in_array($user->role, $roles)) {
            AuditService::logWarning('MEETING_ACCESS_DENIED', 'meetings', 0, [
                'actor_id' => $user->id,
                'required' => $roles,
                'actual' => $user->role,
            ]);
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }
        return $user;
    }

    protected function buildIcal(Meeting $meeting): string
    {
        $format = fn (Carbon $dt) => $dt->copy()->utc()->format('Ymd\THis\Z');
        $uid = 'meeting-' . $meeting->id . '@nkscapital.co.za';
        $summary = addslashes($meeting->title);
        $description = addslashes($meeting->description ?? '');
        $location = addslashes($meeting->location ?? $meeting->meeting_link ?? '');
        $created = $format($meeting->created_at ?? now());
        $dtstart = $format($meeting->start_time);
        $dtend = $format($meeting->end_time);

        return "BEGIN:VCALENDAR\r\n" .
            "VERSION:2.0\r\n" .
            "PRODID:-//NKS Capital//EMS//EN\r\n" .
            "CALSCALE:GREGORIAN\r\n" .
            "METHOD:PUBLISH\r\n" .
            "BEGIN:VEVENT\r\n" .
            "UID:{$uid}\r\n" .
            "DTSTAMP:{$created}\r\n" .
            "DTSTART:{$dtstart}\r\n" .
            "DTEND:{$dtend}\r\n" .
            "SUMMARY:{$summary}\r\n" .
            "DESCRIPTION:{$description}\r\n" .
            "LOCATION:{$location}\r\n" .
            "STATUS:CONFIRMED\r\n" .
            "END:VEVENT\r\n" .
            "END:VCALENDAR\r\n";
    }
}