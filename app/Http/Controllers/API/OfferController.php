<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Offer;
use App\Models\Requisition;
use App\Models\User;
use App\Services\OfferService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class OfferController extends Controller
{
    protected OfferService $offerService;

    public function __construct(OfferService $offerService)
    {
        $this->offerService = $offerService;
    }

    // ============================================================
    // OFFER LIFECYCLE
    // ============================================================

    /**
     * POST /api/v1/recruitment/offer/generate
     */
    public function generate(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'candidate_id' => 'required|exists:candidates,id',
            'salary_offered' => 'required|numeric|min:0',
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'contract_type' => 'nullable|in:permanent,fixed_term,consulting,internship',
            'manager_id' => 'nullable|exists:users,id',
            'notice_period_days' => 'nullable|integer|min:0|max:180',
            'benefits' => 'nullable|array',
            'start_date' => 'required|date|after_or_equal:today',
            'expiry_date' => 'required|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $candidate = Candidate::with('requisition')->findOrFail($request->candidate_id);

        try {
            $offer = $this->offerService->generateOffer($candidate, $request->all(), $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Offer generated',
            'data' => $offer,
        ], 201);
    }

    /**
     * GET /api/v1/recruitment/offer/{id}
     */
    public function show($id)
    {
        $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $offer = Offer::with([
            'candidate.requisition:id,requisition_code,title,department',
            'manager:id,first_name,last_name,email',
            'createdBy:id,first_name,last_name',
            'withdrawnBy:id,first_name,last_name',
        ])->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $offer], 200);
    }

    /**
     * POST /api/v1/recruitment/offer/accept
     */
    public function accept(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'offer_id' => 'required|exists:offers,id',
            'signed_copy' => 'nullable|file|mimes:pdf|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $offer = Offer::findOrFail($request->offer_id);
        $signedCopy = $request->hasFile('signed_copy') ? $request->file('signed_copy') : null;

        try {
            $offer = $this->offerService->acceptOffer($offer, $signedCopy, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Offer accepted',
            'data' => $offer,
        ], 200);
    }

    /**
     * POST /api/v1/recruitment/offer/decline
     */
    public function decline(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'offer_id' => 'required|exists:offers,id',
            'reason' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $offer = Offer::findOrFail($request->offer_id);

        try {
            $offer = $this->offerService->declineOffer($offer, $request->reason, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Offer declined',
            'data' => $offer,
        ], 200);
    }

    /**
     * POST /api/v1/recruitment/offer/send-email
     */
    public function sendEmail(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'offer_id' => 'required|exists:offers,id',
            'custom_message' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $offer = Offer::findOrFail($request->offer_id);

        try {
            $offer = $this->offerService->sendOfferEmail($offer, $user, $request->custom_message);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Offer email sent',
            'data' => $offer,
        ], 200);
    }

    /**
     * POST /api/v1/recruitment/convert-to-employee
     */
    public function convertToEmployee(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'offer_id' => 'required|exists:offers,id',
            'role' => 'nullable|in:employee,manager,director,admin,recruiter',
            'employee_type' => 'nullable|in:ps,pr',
            'service_type' => 'nullable|in:permanent,contractor,temporary,intern',
            'client_id' => 'nullable|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'notes' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $offer = Offer::with('candidate.requisition')->findOrFail($request->offer_id);

        try {
            $employee = $this->offerService->convertToEmployee($offer, $request->all(), $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Candidate converted to employee successfully',
            'data' => [
                'employee' => $employee->load(['manager:id,first_name,last_name']),
                'candidate' => $offer->candidate->fresh(),
                'offer' => $offer->fresh(),
            ]
        ], 201);
    }

    // ============================================================
    // ANALYTICS
    // ============================================================

    /**
     * GET /api/v1/recruitment/metrics
     */
    public function metrics(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->subDays(90)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        $requisitions = Requisition::whereBetween('created_at', [$from, $to])->get();
        $candidates = Candidate::whereBetween('created_at', [$from, $to])->get();
        $offers = Offer::whereBetween('created_at', [$from, $to])->get();

        // Funnel
        $funnel = [
            'applied' => $candidates->count(),
            'screening' => $candidates->whereIn('current_stage', ['screening', 'interview', 'offer', 'hired'])->count(),
            'interview' => $candidates->whereIn('current_stage', ['interview', 'offer', 'hired'])->count(),
            'offer' => $candidates->whereIn('current_stage', ['offer', 'hired'])->count(),
            'hired' => $candidates->where('current_stage', 'hired')->count(),
            'rejected' => $candidates->where('current_stage', 'rejected')->count(),
        ];

        // Conversion rates
        $conversion = [
            'applied_to_interview' => $funnel['applied'] > 0
                ? round(($funnel['interview'] / $funnel['applied']) * 100, 2) : 0,
            'interview_to_offer' => $funnel['interview'] > 0
                ? round(($funnel['offer'] / $funnel['interview']) * 100, 2) : 0,
            'offer_to_hire' => $funnel['offer'] > 0
                ? round(($funnel['hired'] / $funnel['offer']) * 100, 2) : 0,
            'overall_hiring_rate' => $funnel['applied'] > 0
                ? round(($funnel['hired'] / $funnel['applied']) * 100, 2) : 0,
        ];

        // Offer stats
        $offerStats = [
            'total_extended' => $offers->where('status', 'extended')->count(),
            'accepted' => $offers->where('status', 'accepted')->count(),
            'declined' => $offers->where('status', 'declined')->count(),
            'withdrawn' => $offers->where('status', 'withdrawn')->count(),
            'expired' => $offers->where('status', 'expired')->count(),
            'acceptance_rate' => $offers->count() > 0
                ? round(($offers->where('status', 'accepted')->count() / $offers->count()) * 100, 2) : 0,
            'avg_salary_extended' => round((float) $offers->avg('salary_offered'), 2),
        ];

        // Requisition stats
        $requisitionStats = [
            'total' => $requisitions->count(),
            'open' => $requisitions->where('status', 'open')->count(),
            'pending_approval' => $requisitions->where('status', 'pending_approval')->count(),
            'closed' => $requisitions->where('status', 'closed')->count(),
            'by_priority' => [
                'low' => $requisitions->where('priority', 'low')->count(),
                'medium' => $requisitions->where('priority', 'medium')->count(),
                'high' => $requisitions->where('priority', 'high')->count(),
                'critical' => $requisitions->where('priority', 'critical')->count(),
            ],
            'by_department' => $requisitions->groupBy('department')->map->count(),
        ];

        // Time to hire (days from candidate applied → hired)
        $hired = $candidates->where('current_stage', 'hired')->filter(fn ($c) => $c->hire_date);
        $timeToHire = $hired->count() > 0
            ? round($hired->avg(fn ($c) => $c->applied_date->diffInDays($c->hire_date)), 1)
            : null;

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'funnel' => $funnel,
                'conversion' => $conversion,
                'offers' => $offerStats,
                'requisitions' => $requisitionStats,
                'time_to_hire_days' => $timeToHire,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/recruitment/report
     */
    public function report(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'manager', 'director', 'recruiter']);

        $validator = Validator::make($request->all(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'department' => 'nullable|string',
            'format' => 'nullable|in:json,csv',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->subDays(90)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        $query = Candidate::with(['requisition:id,requisition_code,title,department'])
            ->whereBetween('created_at', [$from, $to]);

        if ($request->filled('department')) {
            $query->whereHas('requisition', fn ($q) => $q->where('department', $request->department));
        }

        $candidates = $query->get();

        $report = [
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'total_candidates' => $candidates->count(),
                'by_stage' => $candidates->groupBy('current_stage')->map->count(),
                'by_source' => $candidates->groupBy('source')->map->count(),
                'by_department' => $candidates->groupBy('requisition.department')->map->count(),
                'by_requisition' => $candidates->groupBy('requisition.requisition_code')->map->count(),
            ],
            'candidates' => $candidates->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->full_name,
                'email' => $c->email,
                'requisition' => $c->requisition?->requisition_code,
                'stage' => $c->current_stage,
                'source' => $c->source,
                'applied_date' => $c->applied_date?->toDateString(),
            ]),
        ];

        AuditService::log(
            action: 'RECRUITMENT_REPORT_GENERATED',
            tableName: 'candidates',
            recordId: 0,
            newValues: ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'format' => $request->format ?? 'json'],
            logType: 'success'
        );

        if ($request->format === 'csv') {
            $csv = "Candidate ID,Name,Email,Requisition,Stage,Source,Applied Date\n";
            foreach ($report['candidates'] as $c) {
                $csv .= "{$c['id']},\"{$c['name']}\",{$c['email']},{$c['requisition']},{$c['stage']},{$c['source']},{$c['applied_date']}\n";
            }
            $path = 'reports/recruitment-report-' . time() . '.csv';
            Storage::disk('public')->put($path, $csv);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'download_url' => Storage::url($path),
                    'filename' => basename($path),
                    'summary' => $report['summary'],
                ]
            ], 200);
        }

        return response()->json(['status' => 'success', 'data' => $report], 200);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function requireRole(array $roles): User
    {
        $user = Auth::user();
        if (!in_array($user->role, $roles)) {
            AuditService::logWarning('OFFER_ACCESS_DENIED', 'offers', 0, [
                'actor_id' => $user->id,
                'required' => $roles,
                'actual' => $user->role,
            ]);
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }
        return $user;
    }
}