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
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $gemini->suggestTimesheetEntry(
            $request->project_name,
            $request->date,
            $request->hint
        );

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI is not configured or temporarily unavailable.',
            ], 503);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'text' => $result,
            ],
        ], 200);
    }

    public function autofillTimesheet(
        string $description,
        array $workDays,
        array $projects
    ): ?array {

        $system = 'You are an intelligent timesheet assistant. Generate realistic, professional timesheet entries based strictly on the users supplied work description.

        Rules:
        1. Generate entries only for the supplied working dates.
        2. Use only the supplied projects.
        3. Distribute work reasonably across the available dates and projects.
        4. Do not invent specific meetings, deliverables or achievements.
        5. Keep descriptions concise and professional.
        6. Return valid JSON only, without Markdown or code fences.';

        $prompt = json_encode([
            'description' => $description,
            'work_days' => $workDays,
            'projects' => $projects,
            'required_format' => [
                'entries' => [
                    [
                        'date' => 'YYYY-MM-DD',
                        'project_id' => 1,
                        'project_name' => 'Project name',
                        'description' => 'Professional work description',
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT);

        $response = $this->generate($prompt, $system, 2000);

        if (!$response) {
            return null;
        }

        // Remove Markdown code fences if Gemini includes them.
        $response = preg_replace(
            '/^```(?:json)?\s*|\s*```$/i',
            '',
            trim($response)
        );

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            Log::warning('Gemini returned invalid timesheet JSON.', [
                'response' => substr($response, 0, 1000),
            ]);

            return null;
        }

        $entries = $decoded['entries'] ?? null;

        if (!is_array($entries)) {
            return null;
        }

        // Validate generated entries against the supplied data.
        $allowedDates = array_flip($workDays);

        $allowedProjects = [];

        foreach ($projects as $project) {
            $allowedProjects[$project['id']] = $project['name'];
        }

        $validatedEntries = [];

        foreach ($entries as $entry) {

            if (
                !isset(
                    $entry['date'],
                    $entry['project_id'],
                    $entry['description']
                )
            ) {
                continue;
            }

            $projectId = (int) $entry['project_id'];
            $date = $entry['date'];

            if (
                !isset($allowedDates[$date]) ||
                !isset($allowedProjects[$projectId]) ||
                !is_string($entry['description']) ||
                trim($entry['description']) === ''
            ) {
                continue;
            }

            $validatedEntries[] = [
                'date' => $date,
                'project_id' => $projectId,
                'project_name' => $allowedProjects[$projectId],
                'description' => trim($entry['description']),
            ];
        }

        if (empty($validatedEntries)) {
            Log::warning('Gemini returned no valid timesheet entries.');

            return null;
        }

        return $validatedEntries;
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