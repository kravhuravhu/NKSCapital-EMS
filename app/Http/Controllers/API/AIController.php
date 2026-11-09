<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AIController extends Controller
{
    public function refineLeaveReason(Request $request, GeminiService $gemini)
    {
        $validator = Validator::make($request->all(), [
            'rough_reason' => 'required|string|min:3|max:500',
            'leave_type' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $result = $gemini->refineLeaveReason($request->rough_reason, $request->leave_type);

        if (! $result) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI is not configured or temporarily unavailable.',
            ], 503);
        }

        AuditService::log(
            action: 'AI_LEAVE_REASON_REFINED',
            tableName: 'leave_requests',
            recordId: 0,
            newValues: ['leave_type' => $request->leave_type],
            logType: 'success'
        );

        return response()->json(['status' => 'success', 'data' => ['text' => $result]], 200);
    }

    public function suggestTimesheetEntry(Request $request, GeminiService $gemini)
    {
        $validator = Validator::make($request->all(), [
            'project_name' => 'required|string|max:255',
            'date' => 'required|date',
            'hint' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $result = $gemini->suggestTimesheetEntry(
            $request->project_name,
            $request->date,
            $request->hint
        );

        if (! $result) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI is not configured or temporarily unavailable.',
            ], 503);
        }

        return response()->json(['status' => 'success', 'data' => ['text' => $result]], 200);
    }

    public function autofillTimesheet(Request $request, GeminiService $gemini)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|date',
            'work_days' => 'required|array|min:1',
            'work_days.*' => 'date_format:Y-m-d',
            'projects' => 'required|array|min:1',
            'projects.*.id' => 'required|integer',
            'projects.*.name' => 'required|string',
            'description' => 'required|string|min:20|max:3000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $entries = $gemini->autofillTimesheet(
            $request->description,
            $request->work_days,
            $request->projects
        );

        if ($entries === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI is not configured or returned an unusable response. Try a longer description.',
            ], 503);
        }

        AuditService::log(
            action: 'AI_TIMESHEET_AUTOFILLED',
            tableName: 'timesheet_details',
            recordId: 0,
            newValues: [
                'month' => $request->month,
                'entry_count' => count($entries),
            ],
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'data' => ['entries' => $entries],
        ], 200);
    }

    public function describeIssue(Request $request, GeminiService $gemini)
    {
        $validator = Validator::make($request->all(), [
            'asset_model' => 'required|string|max:255',
            'rough_issue' => 'required|string|min:3|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $result = $gemini->describeAssetIssue($request->asset_model, $request->rough_issue);

        if (! $result) {
            return response()->json(['status' => 'error', 'message' => 'AI is unavailable.'], 503);
        }

        return response()->json(['status' => 'success', 'data' => ['text' => $result]], 200);
    }
}