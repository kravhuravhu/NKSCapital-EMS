<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Requisition;
use App\Models\User;
use App\Services\RecruitmentService;
use App\Services\ResumeParserService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class RecruitmentController extends Controller
{
    protected RecruitmentService $recruitment;
    protected ResumeParserService $parser;

    public function __construct(RecruitmentService $recruitment, ResumeParserService $parser)
    {
        $this->recruitment = $recruitment;
        $this->parser = $parser;
    }

    // ============================================================
    // REQUISITIONS
    // ============================================================

    /**
     * GET /api/v1/recruitment/requisition/list
     */
    public function listRequisitions(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $query = Requisition::with(['createdBy:id,first_name,last_name', 'approvedBy:id,first_name,last_name', 'hiringManager:id,first_name,last_name'])
            ->withCount('candidates');

        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('department')) $query->where('department', $request->department);
        if ($request->filled('priority'))   $query->where('priority', $request->priority);
        if ($request->filled('employee_type_target')) $query->where('employee_type_target', $request->employee_type_target);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('requisition_code', 'like', "%{$s}%")
                  ->orWhere('department', 'like', "%{$s}%");
            });
        }

        $items = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json(['status' => 'success', 'data' => $items], 200);
    }

    /**
     * POST /api/v1/recruitment/requisition/create
     */
    public function createRequisition(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'employment_type' => 'required|in:full_time,part_time,contract',
            'employee_type_target' => 'nullable|in:ps,pr',
            'experience_level' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,critical',
            'required_skills' => 'nullable|array',
            'salary_range_min' => 'nullable|numeric|min:0',
            'salary_range_max' => 'nullable|numeric|gte:salary_range_min',
            'salary_currency' => 'nullable|string|size:3',
            'reason_for_hire' => 'nullable|string',
            'hiring_manager_id' => 'nullable|exists:users,id',
            'target_start_date' => 'nullable|date',
            'requisition_code' => 'nullable|string|unique:requisitions,requisition_code',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $requisition = $this->recruitment->createRequisition($request->all(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Requisition created and pending approval',
            'data' => $requisition,
        ], 201);
    }

    /**
     * POST /api/v1/recruitment/requisition/approve
     */
    public function approveRequisition(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'director']);

        $validator = Validator::make($request->all(), [
            'requisition_id' => 'required|exists:requisitions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $requisition = $this->recruitment->approveRequisition(
                Requisition::findOrFail($request->requisition_id),
                $user,
                $request->all()
            );
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Requisition approved and now open',
            'data' => $requisition,
        ], 200);
    }

    /**
     * PUT /api/v1/recruitment/requisition/update/{id}
     */
    public function updateRequisition(Request $request, $id)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);
        $requisition = Requisition::findOrFail($id);

        if (!$requisition->canUpdate()) {
            return response()->json(['status' => 'error', 'message' => "Cannot update requisition in status {$requisition->status}"], 422);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'department' => 'sometimes|string|max:255',
            'employment_type' => 'sometimes|in:full_time,part_time,contract',
            'employee_type_target' => 'nullable|in:ps,pr',
            'experience_level' => 'nullable|string',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'required_skills' => 'nullable|array',
            'salary_range_min' => 'nullable|numeric|min:0',
            'salary_range_max' => 'nullable|numeric|gte:salary_range_min',
            'salary_currency' => 'nullable|string|size:3',
            'reason_for_hire' => 'nullable|string',
            'hiring_manager_id' => 'nullable|exists:users,id',
            'target_start_date' => 'nullable|date',
            'status' => 'sometimes|in:draft,pending_approval,approved,open,closed,cancelled',
            'rejection_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $old = $requisition->toArray();
        $requisition->update($request->all());

        AuditService::log(
            action: 'REQUISITION_UPDATED',
            tableName: 'requisitions',
            recordId: $requisition->id,
            oldValues: $old,
            newValues: $requisition->fresh()->toArray(),
            logType: 'success'
        );

        return response()->json(['status' => 'success', 'message' => 'Requisition updated', 'data' => $requisition->fresh()], 200);
    }

    // ============================================================
    // CANDIDATES
    // ============================================================

    /**
     * GET /api/v1/recruitment/candidate/list
     */
    public function listCandidates(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $query = Candidate::with(['requisition:id,requisition_code,title,department']);

        if ($request->filled('requisition_id')) $query->where('requisition_id', $request->requisition_id);
        if ($request->filled('stage'))          $query->where('current_stage', $request->stage);
        if ($request->filled('source'))         $query->where('source', $request->source);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        // Filter by skills (JSON contains)
        if ($request->filled('skill')) {
            $query->whereJsonContains('skills', $request->skill);
        }

        $items = $query->orderBy('applied_date', 'desc')->paginate($request->per_page ?? 15);

        return response()->json(['status' => 'success', 'data' => $items], 200);
    }

    /**
     * GET /api/v1/recruitment/candidate/{id}
     */
    public function showCandidate($id)
    {
        $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $candidate = Candidate::with([
            'requisition:id,requisition_code,title,department,status',
            'interviews.interviewer:id,first_name,last_name',
            'hiredEmployee:id,first_name,last_name,employee_number',
            'rejectedBy:id,first_name,last_name',
        ])->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $candidate], 200);
    }

    /**
     * POST /api/v1/recruitment/candidate/add
     */
    public function addCandidate(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'requisition_id' => 'required|exists:requisitions,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:candidates,email',
            'phone' => 'nullable|string|max:50',
            'years_experience' => 'nullable|integer|min:0|max:60',
            'expected_salary' => 'nullable|numeric|min:0',
            'skills' => 'nullable|array',
            'source' => 'nullable|string|max:100',
            'linkedin_url' => 'nullable|url',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
            'resume' => 'nullable|file|mimes:pdf,docx,txt|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $requisition = Requisition::findOrFail($request->requisition_id);

        if (!$requisition->isOpenForApplications() && !in_array($user->role, ['admin', 'super_admin', 'director'])) {
            return response()->json(['status' => 'error', 'message' => 'Requisition is not open for applications'], 422);
        }

        $resumePath = null;
        $resumeHash = null;
        if ($request->hasFile('resume')) {
            $file = $request->file('resume');
            $filename = 'candidate-resume-' . time() . '-' . $file->getClientOriginalName();
            $resumePath = $file->storeAs('candidate-resumes', $filename, 'public');
            $resumeHash = hash_file('sha256', $file->getRealPath());
        }

        $candidate = $this->recruitment->addCandidate(
            $requisition,
            $request->all(),
            $user,
            $resumePath,
            $resumeHash
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Candidate added',
            'data' => $candidate,
        ], 201);
    }

    /**
     * POST /api/v1/recruitment/candidate/parse-resume
     * Parse resume and return extracted fields (does not persist).
     */
    public function parseResume(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'resume' => 'required|file|mimes:pdf,docx,txt|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $result = $this->parser->parse($request->file('resume'));

        AuditService::log(
            action: 'CANDIDATE_RESUME_PARSED',
            tableName: 'candidates',
            recordId: 0,
            newValues: [
                'success' => $result['success'],
                'extracted_email' => $result['data']['email'] ?? null,
                'extracted_name' => $result['data']['full_name'] ?? null,
            ],
            logType: $result['success'] ? 'success' : 'warning'
        );

        return response()->json([
            'status' => $result['success'] ? 'success' : 'warning',
            'message' => $result['message'],
            'data' => $result['data'],
        ], $result['success'] ? 200 : 422);
    }

    /**
     * PUT /api/v1/recruitment/candidate/stage
     */
    public function advanceStage(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'candidate_id' => 'required|exists:candidates,id',
            'stage' => 'required|in:applied,screening,interview,offer,hired,rejected,offer_declined,withdrawn,on_hold',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $candidate = Candidate::findOrFail($request->candidate_id);

        if (!$candidate->canAdvanceStage()) {
            return response()->json(['status' => 'error', 'message' => "Cannot advance candidate in stage {$candidate->current_stage}"], 422);
        }

        $candidate = $this->recruitment->advanceStage($candidate, $request->stage, $user, $request->notes);

        return response()->json(['status' => 'success', 'message' => 'Candidate stage updated', 'data' => $candidate], 200);
    }

    /**
     * POST /api/v1/recruitment/candidate/reject
     */
    public function rejectCandidate(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'candidate_id' => 'required|exists:candidates,id',
            'reason' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $candidate = Candidate::findOrFail($request->candidate_id);

        if (!$candidate->canBeRejected()) {
            return response()->json(['status' => 'error', 'message' => "Cannot reject candidate in stage {$candidate->current_stage}"], 422);
        }

        $candidate = $this->recruitment->rejectCandidate($candidate, $request->reason, $user);

        return response()->json(['status' => 'success', 'message' => 'Candidate rejected', 'data' => $candidate], 200);
    }

    // ============================================================
    // INTERVIEWS
    // ============================================================

    /**
     * GET /api/v1/recruitment/interview/list
     */
    public function listInterviews(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $query = Interview::with([
            'candidate:id,first_name,last_name,email,current_stage',
            'interviewer:id,first_name,last_name,email',
        ]);

        if ($request->filled('status'))         $query->where('status', $request->status);
        if ($request->filled('interviewer_id')) $query->where('interviewer_id', $request->interviewer_id);
        if ($request->filled('candidate_id'))   $query->where('candidate_id', $request->candidate_id);
        if ($request->filled('from'))           $query->where('scheduled_at', '>=', $request->from);
        if ($request->filled('to'))             $query->where('scheduled_at', '<=', $request->to);

        $items = $query->orderBy('scheduled_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json(['status' => 'success', 'data' => $items], 200);
    }

    /**
     * GET /api/v1/recruitment/interview/{id}
     */
    public function showInterview($id)
    {
        $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $interview = Interview::with([
            'candidate.requisition:id,requisition_code,title,department',
            'interviewer:id,first_name,last_name,email',
            'cancelledBy:id,first_name,last_name',
        ])->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $interview], 200);
    }

    /**
     * GET /api/v1/recruitment/interview/calendar
     */
    public function interviewCalendar(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'month' => 'nullable|date_format:Y-m',
            'interviewer_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $month = $request->filled('month') ? Carbon::parse($request->month . '-01') : now()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $query = Interview::with([
            'candidate:id,first_name,last_name,email',
            'interviewer:id,first_name,last_name',
        ])->whereBetween('scheduled_at', [$start, $end]);

        if ($request->filled('interviewer_id')) {
            $query->where('interviewer_id', $request->interviewer_id);
        }

        $interviews = $query->orderBy('scheduled_at')->get();

        $grouped = $interviews->groupBy(fn ($i) => $i->scheduled_at->format('Y-m-d'))
            ->map(function ($dayItems, $date) {
                return [
                    'date' => $date,
                    'count' => $dayItems->count(),
                    'interviews' => $dayItems->map(fn ($i) => [
                        'id' => $i->id,
                        'time' => $i->scheduled_at->format('H:i'),
                        'duration' => $i->duration_minutes,
                        'candidate' => $i->candidate?->full_name,
                        'interviewer' => $i->interviewer?->full_name,
                        'status' => $i->status,
                        'type' => $i->interview_type,
                    ]),
                ];
            })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'month' => $month->format('F Y'),
                'total' => $interviews->count(),
                'by_date' => $grouped,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/recruitment/interview/schedule
     */
    public function scheduleInterview(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'candidate_id' => 'required|exists:candidates,id',
            'interviewer_id' => 'required|exists:users,id',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'interview_type' => 'nullable|in:phone,video,in_person',
            'round' => 'nullable|integer|min:1|max:10',
            'location_or_link' => 'nullable|string|max:500',
            'meeting_link' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $candidate = Candidate::findOrFail($request->candidate_id);
        $interviewer = User::findOrFail($request->interviewer_id);

        try {
            $interview = $this->recruitment->scheduleInterview($candidate, $interviewer, $request->all(), $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Interview scheduled',
            'data' => $interview,
        ], 201);
    }

    /**
     * POST /api/v1/recruitment/interview/feedback
     */
    public function submitFeedback(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'interview_id' => 'required|exists:interviews,id',
            'rating' => 'required|integer|min:1|max:5',
            'recommendation' => 'required|in:strong_yes,yes,no,strong_no,undecided',
            'notes' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $interview = Interview::findOrFail($request->interview_id);

        try {
            $interview = $this->recruitment->submitFeedback($interview, $user, $request->all());
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'message' => 'Feedback submitted', 'data' => $interview], 200);
    }

    /**
     * PUT /api/v1/recruitment/interview/reschedule/{id}
     */
    public function rescheduleInterview(Request $request, $id)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'location_or_link' => 'nullable|string|max:500',
            'meeting_link' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $interview = Interview::findOrFail($id);

        try {
            $interview = $this->recruitment->rescheduleInterview($interview, $request->all(), $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'message' => 'Interview rescheduled', 'data' => $interview], 200);
    }

    /**
     * POST /api/v1/recruitment/interview/cancel/{id}
     */
    public function cancelInterview(Request $request, $id)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $interview = Interview::findOrFail($id);

        try {
            $interview = $this->recruitment->cancelInterview($interview, $request->reason, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'message' => 'Interview cancelled', 'data' => $interview], 200);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function requireRole(array $roles): User
    {
        $user = Auth::user();
        if (!in_array($user->role, $roles)) {
            AuditService::logWarning('RECRUITMENT_ACCESS_DENIED', 'recruitment', 0, [
                'actor_id' => $user->id,
                'required' => $roles,
                'actual' => $user->role,
            ]);
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }
        return $user;
    }
}