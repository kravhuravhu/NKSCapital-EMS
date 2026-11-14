<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\Client;
use App\Models\User;
use App\Models\TimesheetDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\ProjectChangeRequest;
use App\Models\Notification;

class ProjectController extends Controller
{
    /**
     * GET /api/v1/project/list
     * List all projects with filters
     */
    public function listProjects(Request $request)
    {
        $query = Project::with(['client', 'projectManager']);

        // Filters
        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('project_type')) {
            $query->where('project_type', $request->project_type);
        }
        if ($request->has('project_manager_id')) {
            $query->where('project_manager_id', $request->project_manager_id);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('project_code', 'LIKE', "%{$search}%");
            });
        }

        // count only active assignments — removed team members should not inflate the count
        $query->withCount([
            'assignments' => fn ($q) => $q->where('is_active', true),
        ]);

        $projects = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => 'success',
            'data' => $projects,
        ], 200);
    }

    /**
     * GET /api/v1/project/{id}
     * Get project details
     */
    public function getProject($id)
    {
        $project = Project::with([
            'client',
            'projectManager',
            'assignments' => fn ($q) => $q->where('is_active', true),
            'assignments.user',
            'timesheetDetails',
        ])->findOrFail($id);

        // Calculate total hours logged
        $totalHours = $project->timesheetDetails()->sum('hours_worked');

        return response()->json([
            'status' => 'success',
            'data' => [
                'project' => $project,
                'total_hours_logged' => $totalHours,
                'remaining_hours' => $project->budgeted_hours - $totalHours,
                'assigned_employees' => $project->assignments->map(function ($assignment) {
                    return [
                        'user' => $assignment->user,
                        'role' => $assignment->role,
                        'allocation' => $assignment->allocation_percentage,
                        'assigned_date' => $assignment->assigned_date,
                    ];
                }),
            ],
        ], 200);
    }

    /**
     * POST /api/v1/project/create
     * Create new project
     */
    public function createProject(Request $request)
    {
        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to create projects'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:clients,id',
            'project_code' => 'required|string|unique:projects,project_code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_type' => 'required|in:client,internal,hybrid',
            'budgeted_hours' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_manager_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $project = Project::create([
            'client_id' => $request->client_id,
            'project_code' => $request->project_code,
            'name' => $request->name,
            'description' => $request->description,
            'project_type' => $request->project_type,
            'budgeted_hours' => $request->budgeted_hours,
            'hourly_rate' => $request->hourly_rate,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'project_manager_id' => $request->project_manager_id,
            'status' => 'planning',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Project created successfully',
            'data' => $project
        ], 201);
    }

    /**
     * PUT /api/v1/project/update/{id}
     * Update project
     */
    public function updateProject(Request $request, $id)
    {
        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to update projects',
            ], 403);
        }

        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'client_id' => 'sometimes|exists:clients,id',
            'project_code' => 'sometimes|string|unique:projects,project_code,' . $id,
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'project_type' => 'sometimes|in:client,internal,hybrid',
            'budgeted_hours' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_manager_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:planning,active,on_hold,completed,archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $project->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Project updated successfully',
            'data' => $project->fresh(),
        ], 200);
    }

    /**
     * POST /api/v1/project/archive/{id}
     * Archive completed project
     */
    public function archiveProject($id)
    {
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to archive projects',
            ], 403);
        }

        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found',
            ], 404);
        }

        $project->status = 'archived';
        $project->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Project archived successfully',
            'data' => [
                'project_id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/project/assign-employee
     * Assign employee to project
     */
    public function assignEmployee(Request $request)
    {
        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to assign employees',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|in:team_member,lead,senior,junior',
            'allocation_percentage' => 'nullable|numeric|min:1|max:100',
            'assigned_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:assigned_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $project = Project::find($request->project_id);

        if (!$project) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found',
            ], 404);
        }

        $existing = ProjectAssignment::where('project_id', $project->id)
            ->where('user_id', $request->user_id)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee is already assigned to this project',
            ], 409);
        }

        $assignment = ProjectAssignment::create([
            'project_id' => $project->id,
            'user_id' => $request->user_id,
            'role' => $request->role ?? 'team_member',
            'allocation_percentage' => $request->allocation_percentage ?? 100,
            'assigned_date' => $request->assigned_date ?? now(),
            'end_date' => $request->end_date,
            'is_active' => true,
        ]);

        // Update user's project_id if not set
        $user = User::find($request->user_id);

        if ($user && !$user->project_id) {
            $user->project_id = $project->id;
            $user->employee_type = 'pr';
            $user->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Employee assigned to project successfully',
            'data' => [
                'assignment' => $assignment,
                'user' => $user->only(['id', 'first_name', 'last_name', 'email']),
                'project' => $project->only(['id', 'name', 'project_code']),
            ],
        ], 201);
    }

    /**
     * POST /api/v1/project/remove-employee
     * Remove employee from project
     */
    public function removeEmployee(Request $request)
    {
        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to remove employees'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $assignment = ProjectAssignment::where('project_id', $request->project_id)
            ->where('user_id', $request->user_id)
            ->where('is_active', true)
            ->first();

        if (!$assignment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee is not assigned to this project'
            ], 404);
        }

        $assignment->is_active = false;
        $assignment->end_date = now();
        $assignment->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Employee removed from project successfully',
            'data' => [
                'project_id' => $request->project_id,
                'user_id' => $request->user_id,
                'removed_at' => now(),
            ]
        ], 200);
    }

    /**
     * POST /api/v1/project/assign-manager
     * Assign project manager (L1 approver)
     */
    public function assignProjectManager(Request $request)
    {
        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to assign project managers',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'project_manager_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $project = Project::find($request->project_id);

        if (!$project) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found',
            ], 404);
        }

        $manager = User::find($request->project_manager_id);

        if (!$manager) {
            return response()->json([
                'status' => 'error',
                'message' => 'Manager not found',
            ], 404);
        }

        $project->project_manager_id = $manager->id;
        $project->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Project manager assigned successfully',
            'data' => [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'project_manager' => [
                    'id' => $manager->id,
                    'name' => $manager->full_name,
                    'email' => $manager->email,
                ],
                'is_l1_approver' => true,
            ],
        ], 200);
    }

    /**
     * GET /api/v1/project/list/assigned
     * Returns every active project the user is assigned to, with client,
     * project manager, team and my engagement. Budget and cost are not
     * returned here so employees never see them.
     */
    public function listAssignedProjects(Request $request)
    {
        $user = Auth::user();

        $assignments = ProjectAssignment::with([
            'project.client:id,company_name,registration_number,vat_number,billing_address,primary_contact_name,primary_contact_email,primary_contact_phone,status,is_active',
            'project.projectManager:id,first_name,last_name,email,position,department,profile_photo',
        ])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        $projectIds = $assignments->pluck('project_id')
            ->merge($user->project_id ? [$user->project_id] : [])
            ->unique()
            ->filter()
            ->values();

        $teammatesByProject = ProjectAssignment::query()
            ->whereIn('project_id', $projectIds)
            ->where('is_active', true)
            ->where('user_id', '!=', $user->id)
            ->with('user:id,first_name,last_name,position,department,profile_photo,employee_number')
            ->get()
            ->groupBy('project_id');

        $primaryProject = $user->project_id
            ? Project::with([
                'client',
                'projectManager:id,first_name,last_name,email,position,department',
            ])->find($user->project_id)
            : null;

        $format = function ($project, $assignment = null) use ($teammatesByProject, $primaryProject) {
            if (!$project) return null;

            $teammates = $teammatesByProject->get($project->id, collect());

            return [
                'id' => $project->id,
                'name' => $project->name,
                'project_code' => $project->project_code,
                'description' => $project->description,
                'project_type' => $project->project_type,
                'status' => $project->status,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'is_primary' => $primaryProject && $primaryProject->id === $project->id,
                'client' => $project->client ? [
                    'id' => $project->client->id,
                    'company_name' => $project->client->company_name,
                    'registration_number' => $project->client->registration_number,
                    'primary_contact_name' => $project->client->primary_contact_name,
                    'primary_contact_email' => $project->client->primary_contact_email,
                    'primary_contact_phone' => $project->client->primary_contact_phone,
                    'billing_address' => $project->client->billing_address,
                ] : null,
                'project_manager' => $project->projectManager ? [
                    'id' => $project->projectManager->id,
                    'name' => $project->projectManager->full_name,
                    'email' => $project->projectManager->email,
                    'position' => $project->projectManager->position,
                    'department' => $project->projectManager->department,
                ] : null,
                'my_engagement' => $assignment ? [
                    'role' => $assignment->role,
                    'allocation' => (float) $assignment->allocation_percentage,
                    'assigned_date' => $assignment->assigned_date?->toDateString(),
                    'end_date' => $assignment->end_date?->toDateString(),
                ] : null,
                'team' => [
                    'count' => $teammates->count() + 1,
                    'teammates' => $teammates->map(fn ($a) => [
                        'id' => $a->user?->id,
                        'name' => $a->user?->full_name,
                        'position' => $a->user?->position,
                        'role' => $a->role,
                    ])->values(),
                ],
            ];
        };

        $formatted = $assignments->map(fn ($a) => $format($a->project, $a))->filter()->values();

        if ($primaryProject && !$formatted->contains('id', $primaryProject->id)) {
            $formatted->prepend($format($primaryProject));
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'primary_project' => $primaryProject ? $format($primaryProject) : null,
                'assigned_projects' => $formatted,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/project/request-change
     */
    public function requestChange(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:swap,change,concern,additional,meeting',
            'current_project_id' => 'nullable|exists:projects,id',
            'target_project_id' => 'nullable|exists:projects,id',
            'reason' => 'required|string|min:20|max:2000',
            'requested_role' => 'nullable|in:team_member,lead,senior,junior',
            'requested_allocation' => 'nullable|numeric|min:1|max:100',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (in_array($request->type, ['swap', 'change', 'concern'], true)) {
            if (!$request->current_project_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Select which project this request concerns.',
                ], 422);
            }

            $isAssigned = ProjectAssignment::where('user_id', $user->id)
                ->where('project_id', $request->current_project_id)
                ->where('is_active', true)
                ->exists();

            if (!$isAssigned) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not assigned to that project.',
                ], 403);
            }
        }

        if ($request->type === 'swap' && !$request->target_project_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'A swap request needs a target project.',
            ], 422);
        }

        $openExists = ProjectChangeRequest::where('user_id', $user->id)
            ->where('current_project_id', $request->current_project_id)
            ->whereIn('status', ['pending', 'acknowledged'])
            ->exists();

        if ($openExists) {
            return response()->json([
                'status' => 'error',
                'message' => 'You already have an open request for this project.',
            ], 422);
        }

        $row = ProjectChangeRequest::create([
            'user_id' => $user->id,
            'current_project_id' => $request->current_project_id,
            'target_project_id' => $request->target_project_id,
            'type' => $request->type,
            'reason' => $request->reason,
            'requested_role' => $request->requested_role,
            'requested_allocation' => $request->requested_allocation,
            'priority' => $request->priority ?? 'medium',
            'status' => 'pending',
        ]);

        AuditService::log(
            action: 'PROJECT_CHANGE_REQUESTED',
            tableName: 'project_change_requests',
            recordId: $row->id,
            newValues: [
                'type' => $row->type,
                'current_project_id' => $row->current_project_id,
                'target_project_id' => $row->target_project_id,
                'priority' => $row->priority,
            ],
            logType: 'success'
        );

        $project = $row->current_project_id ? Project::find($row->current_project_id) : null;
        $recipients = collect();

        if ($project?->project_manager_id) {
            $recipients->push($project->project_manager_id);
        } else {
            $recipients = $recipients->merge(
                User::whereIn('role', ['director', 'operational_manager', 'admin'])
                    ->where('is_active', true)
                    ->pluck('id')
            );
        }

        foreach ($recipients->unique() as $recipientId) {
            Notification::create([
                'user_id' => $recipientId,
                'type' => 'PROJECT_CHANGE_REQUESTED',
                'title' => 'Project ' . ucfirst($row->type) . ' Request',
                'message' => "{$user->full_name} submitted a {$row->type} request for " . ($project?->name ?? 'a project') . '.',
                'reference_id' => $row->id,
                'reference_type' => ProjectChangeRequest::class,
                'category' => 'project',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Request submitted. Your project manager has been notified.',
            'data' => $row->load(['currentProject:id,name,project_code', 'targetProject:id,name,project_code']),
        ], 201);
    }

    /**
     * GET /api/v1/project/requests
     */
    public function listRequests(Request $request)
    {
        $user = Auth::user();

        $query = ProjectChangeRequest::with([
            'currentProject:id,name,project_code',
            'targetProject:id,name,project_code',
            'reviewer:id,first_name,last_name',
        ])->where('user_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $items = $query->orderByDesc('created_at')->limit(50)->get();

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ], 200);
    }

    /**
     * GET /api/v1/project/hours/{id}
     * Get project hours summary
     */
    public function getProjectHours($id)
    {
        $project = Project::findOrFail($id);

        // Get hours by employee
        $hoursByEmployee = TimesheetDetail::where('project_id', $id)
            ->selectRaw('user_id, SUM(hours_worked) as total_hours')
            ->groupBy('user_id')
            ->with('user:id,first_name,last_name,employee_number')
            ->get();

        // Get total hours
        $totalHours = TimesheetDetail::where('project_id', $id)->sum('hours_worked');

        // Get hours by month
        $hoursByMonth = TimesheetDetail::where('project_id', $id)
            ->selectRaw('DATE_FORMAT(work_date, "%Y-%m") as month, SUM(hours_worked) as total_hours')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'project' => $project->only(['id', 'name', 'project_code', 'budgeted_hours']),
                'total_hours_logged' => $totalHours,
                'remaining_hours' => $project->budgeted_hours - $totalHours,
                'utilization_percentage' => $project->budgeted_hours > 0 
                    ? round(($totalHours / $project->budgeted_hours) * 100, 2) 
                    : 0,
                'hours_by_employee' => $hoursByEmployee,
                'hours_by_month' => $hoursByMonth,
            ]
        ], 200);
    }
}