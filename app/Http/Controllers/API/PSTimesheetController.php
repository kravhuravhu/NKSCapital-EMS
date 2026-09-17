<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PSTimesheet;
use App\Models\TimesheetDetail;
use App\Models\Client;
use App\Models\ClientManager;
use App\Models\Project;
use App\Models\User;
use App\Models\Notification;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Models\LeaveCalendar;

class PSTimesheetController extends Controller
{
    /**
     * POST /api/v1/timesheet/ps/create
     * Create a new PS monthly timesheet
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        // Validate user is PS employee
        if ($user->employee_type !== 'ps') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Professional Services employees can create PS timesheets'
            ], 403);
        }

        if (!$user->client_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'No client assigned to your profile. Contact admin.'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'month_year' => 'required|date_format:Y-m-d',
            'entries' => 'required|array|min:1',
            'entries.*.work_date' => 'required|date',
            'entries.*.project_id' => 'required|exists:projects,id',
            'entries.*.hours_worked' => 'required|numeric|min:0.5|max:24',
            'entries.*.task_description' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $monthYear = Carbon::parse($request->month_year)->startOfMonth();

        // Check if timesheet already exists
        $existing = PSTimesheet::where('user_id', $user->id)
            ->where('month_year', $monthYear->format('Y-m-d'))
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Timesheet already exists for this month',
                'data' => ['timesheet_id' => $existing->id]
            ], 409);
        }

        // Create timesheet
        $timesheet = PSTimesheet::create([
            'user_id' => $user->id,
            'month_year' => $monthYear->format('Y-m-d'),
            'status' => 'draft',
            'notes' => $request->notes,
        ]);
        
        // Check for leave day conflicts
        $leaveConflicts = [];
        foreach ($request->entries as $entry) {
            $workDate = $entry['work_date'];
            $leaveEntry = LeaveCalendar::where('user_id', $user->id)
                ->where('leave_date', $workDate)
                ->where(function ($q) {
                    $q->where('is_approved', true)
                      ->orWhere('is_pending_proof', true)
                      ->orWhere('is_unpaid_conversion', true);
                })
                ->first();

            if ($leaveEntry) {
                $leaveConflicts[] = [
                    'date' => $workDate,
                    'leave_type' => $leaveEntry->leave_type,
                    'is_pending_proof' => (bool) $leaveEntry->is_pending_proof,
                    'is_unpaid_conversion' => (bool) $leaveEntry->is_unpaid_conversion,
                ];
            }
        }

        if (!empty($leaveConflicts)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot add work hours on approved leave days',
                'conflicts' => $leaveConflicts,
            ], 422);
        }

        // Create details
        $totalHours = 0;
        foreach ($request->entries as $entry) {
            TimesheetDetail::create([
                'timesheet_parent_id' => $timesheet->id,
                'timesheet_parent_type' => PSTimesheet::class,
                'work_date' => $entry['work_date'],
                'project_id' => $entry['project_id'],
                'hours_worked' => $entry['hours_worked'],
                'is_overtime' => false,
                'task_description' => $entry['task_description'] ?? null,
                'is_billable' => true,
            ]);
            $totalHours += $entry['hours_worked'];
        }

        // Audit log using service
        AuditService::log(
            action: 'PS_TIMESHEET_CREATED',
            tableName: 'ps_timesheets',
            recordId: $timesheet->id,
            newValues: ['total_hours' => $totalHours, 'month_year' => $monthYear->format('Y-m-d')]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'PS timesheet created successfully',
            'data' => [
                'timesheet' => $timesheet->load('details'),
                'total_hours' => $totalHours,
            ]
        ], 201);
    }

    /**
     * POST /api/v1/timesheet/ps/generate-template
     * Generate client-branded PDF timesheet template
     */
    public function generateTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:ps_timesheets,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $timesheet = PSTimesheet::with(['details.project', 'user.client'])
            ->where('user_id', $user->id)
            ->findOrFail($request->timesheet_id);

        if (!in_array($timesheet->status, ['draft', 'template_generated'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot generate template for timesheet in current status',
                'current_status' => $timesheet->status
            ], 422);
        }

        $client = $user->client;

        // Generate PDF
        $pdf = Pdf::loadView('pdf.ps-timesheet-template', [
            'timesheet' => $timesheet,
            'user' => $user,
            'client' => $client,
            'details' => $timesheet->details,
            'generated_at' => now()->format('d M Y H:i'),
        ]);

        // Store PDF
        $filename = 'ps-timesheet-' . $user->id . '-' . $timesheet->month_year->format('Y-m') . '.pdf';
        $path = 'ps-timesheets/' . $filename;
        Storage::disk('public')->put($path, $pdf->output());
        $pdfHash = hash('sha256', $pdf->output());

        // Update timesheet
        $timesheet->status = 'template_generated';
        $timesheet->template_generated_at = now();
        $timesheet->save();

        // Audit log
        AuditService::log(
            action: 'PS_TEMPLATE_GENERATED',
            tableName: 'ps_timesheets',
            recordId: $timesheet->id,
            newValues: ['pdf_hash' => $pdfHash, 'template_generated_at' => now()->toIso8601String()]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Client template generated successfully',
            'data' => [
                'timesheet_id' => $timesheet->id,
                'status' => $timesheet->status,
                'template_generated_at' => $timesheet->template_generated_at,
                'download_url' => Storage::url($path),
                'pdf_hash' => $pdfHash,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/ps/download-template
     */
    public function downloadTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:ps_timesheets,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $timesheet = PSTimesheet::where('user_id', Auth::id())
            ->findOrFail($request->timesheet_id);

        if ($timesheet->status === 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Please generate the template first'
            ], 422);
        }

        $filename = 'ps-timesheet-' . $timesheet->user_id . '-' . $timesheet->month_year->format('Y-m') . '.pdf';
        $path = 'ps-timesheets/' . $filename;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template file not found. Please regenerate.'
            ], 404);
        }

        return response()->download(
            Storage::disk('public')->path($path),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * POST /api/v1/timesheet/ps/upload-signed
     */
    public function uploadSigned(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:ps_timesheets,id',
            'signed_file' => 'required|file|mimes:pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $timesheet = PSTimesheet::where('user_id', $user->id)
            ->findOrFail($request->timesheet_id);

        if ($timesheet->status !== 'template_generated') {
            return response()->json([
                'status' => 'error',
                'message' => 'Timesheet must be in template_generated status',
                'current_status' => $timesheet->status
            ], 422);
        }

        $file = $request->file('signed_file');
        $filename = 'ps-timesheet-signed-' . $timesheet->id . '-' . time() . '.pdf';
        $path = $file->storeAs('ps-timesheets/signed', $filename, 'public');
        $fileHash = hash_file('sha256', $file->getRealPath());

        $timesheet->signed_pdf_path = $path;
        $timesheet->signed_pdf_hash = $fileHash;
        $timesheet->client_signature_date = now();
        $timesheet->status = 'external_signed';
        $timesheet->save();

        // Audit log
        AuditService::log(
            action: 'PS_SIGNED_UPLOADED',
            tableName: 'ps_timesheets',
            recordId: $timesheet->id,
            newValues: [
                'signed_pdf_hash' => $fileHash,
                'client_signature_date' => now()->toIso8601String()
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Signed timesheet uploaded successfully',
            'data' => [
                'timesheet_id' => $timesheet->id,
                'status' => $timesheet->status,
                'client_signature_date' => $timesheet->client_signature_date,
                'signed_pdf_hash' => $fileHash,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/timesheet/ps/send-to-client
     */
    public function sendToClient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:ps_timesheets,id',
            'client_manager_email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $timesheet = PSTimesheet::where('user_id', $user->id)
            ->findOrFail($request->timesheet_id);

        if ($timesheet->status !== 'external_signed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Signed timesheet must be uploaded first',
                'current_status' => $timesheet->status
            ], 422);
        }

        $timesheet->client_manager_email = $request->client_manager_email;
        $timesheet->client_emailed_at = now();
        $timesheet->status = 'submitted_to_client';
        $timesheet->save();

        // Send copy to L2 (Director) for storage
        $directors = User::where('role', 'director')->where('is_active', true)->get();
        foreach ($directors as $director) {
            Notification::create([
                'user_id' => $director->id,
                'type' => 'PS_TIMESHEET_STORED',
                'title' => 'PS Timesheet Stored',
                'message' => "PS timesheet for {$user->full_name} ({$timesheet->month_year->format('F Y')}) has been submitted and stored.",
                'reference_id' => $timesheet->id,
                'reference_type' => PSTimesheet::class,
                'is_read' => false,
            ]);
        }

        // Audit log
        AuditService::log(
            action: 'PS_EMAILED_TO_CLIENT',
            tableName: 'ps_timesheets',
            recordId: $timesheet->id,
            newValues: [
                'client_email' => $request->client_manager_email,
                'emailed_at' => now()->toIso8601String(),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Timesheet sent to client and stored for L2',
            'data' => [
                'timesheet_id' => $timesheet->id,
                'status' => $timesheet->status,
                'client_emailed_at' => $timesheet->client_emailed_at,
                'client_email' => $timesheet->client_manager_email,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/ps/{id}
     */
    public function show($id)
    {
        $user = Auth::user();
        $timesheet = PSTimesheet::with(['details.project', 'user.client', 'clientManager'])
            ->findOrFail($id);

        if ($user->id !== $timesheet->user_id && !in_array($user->role, ['admin', 'director', 'manager'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to view this timesheet'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'timesheet' => $timesheet,
                'total_hours' => $timesheet->total_hours,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/ps/history
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        $timesheets = PSTimesheet::with(['details'])
            ->where('user_id', $user->id)
            ->orderBy('month_year', 'desc')
            ->limit(12)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $timesheets->map(function($ts) {
                return [
                    'id' => $ts->id,
                    'month_year' => $ts->month_year->format('F Y'),
                    'status' => $ts->status,
                    'total_hours' => $ts->total_hours,
                    'template_generated_at' => $ts->template_generated_at,
                    'client_signature_date' => $ts->client_signature_date,
                    'client_emailed_at' => $ts->client_emailed_at,
                ];
            })
        ], 200);
    }

    /**
     * POST /api/v1/timesheet/ps/recall
     */
    public function recall(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:ps_timesheets,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $timesheet = PSTimesheet::where('user_id', $user->id)
            ->findOrFail($request->timesheet_id);

        if (!$timesheet->canRecall()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot recall timesheet in current status',
                'current_status' => $timesheet->status
            ], 422);
        }

        $oldValues = [
            'status' => $timesheet->status,
            'template_generated_at' => $timesheet->template_generated_at?->toIso8601String(),
            'client_signature_date' => $timesheet->client_signature_date?->toIso8601String(),
        ];

        $timesheet->status = 'draft';
        $timesheet->template_generated_at = null;
        $timesheet->client_signature_date = null;
        $timesheet->client_emailed_at = null;
        $timesheet->client_manager_email = null;
        $timesheet->signed_pdf_path = null;
        $timesheet->signed_pdf_hash = null;
        $timesheet->save();

        // Audit log
        AuditService::log(
            action: 'PS_TIMESHEET_RECALLED',
            tableName: 'ps_timesheets',
            recordId: $timesheet->id,
            oldValues: $oldValues,
            newValues: ['status' => 'draft']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Timesheet recalled successfully',
            'data' => ['timesheet_id' => $timesheet->id, 'status' => $timesheet->status]
        ], 200);
    }
}