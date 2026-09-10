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
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('project_code', 'LIKE', "%{$search}%");
            });
        }

        // Load counts
        $query->withCount('assignments');

        $projects = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => 'success',
            'data' => $projects
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
            'assignments.user',
            'timesheetDetails'
        ])->findOrFail($id);

        // Calculate total hours logged
        $totalHours = $project->timesheetDetails()->sum('hours_worked');

        return response()->json([
            'status' => 'success',
            'data' => [
                'project' => $project,
                'total_hours_logged' => $totalHours,
                'remaining_hours' => $project->budgeted_hours - $totalHours,
                'assigned_employees' => $project->assignments->map(function($assignment) {
                    return [
                        'user' => $assignment->user,
                        'role' => $assignment->role,
                        'allocation' => $assignment->allocation_percentage,
                        'assigned_date' => $assignment->assigned_date,
                    ];
                }),
            ]
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
        $project = Project::findOrFail($id);

        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to update projects'
            ], 403);
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
                'errors' => $validator->errors()
            ], 422);
        }

        $project->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Project updated successfully',
            'data' => $project
        ], 200);
    }

    /**
     * POST /api/v1/project/archive/{id}
     * Archive completed project
     */
    public function archiveProject($id)
    {
        $project = Project::findOrFail($id);

        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to archive projects'
            ], 403);
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
            ]
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
                'message' => 'Unauthorized to assign employees'
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
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if already assigned
        $existing = ProjectAssignment::where('project_id', $request->project_id)
            ->where('user_id', $request->user_id)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee is already assigned to this project'
            ], 409);
        }

        $assignment = ProjectAssignment::create([
            'project_id' => $request->project_id,
            'user_id' => $request->user_id,
            'role' => $request->role ?? 'team_member',
            'allocation_percentage' => $request->allocation_percentage ?? 100,
            'assigned_date' => $request->assigned_date ?? now(),
            'end_date' => $request->end_date,
            'is_active' => true,
        ]);

        // Update user's project_id if not set
        $user = User::find($request->user_id);
        if (!$user->project_id) {
            $user->project_id = $request->project_id;
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
            ]
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
                'message' => 'Unauthorized to assign project managers'
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
                'errors' => $validator->errors()
            ], 422);
        }

        $project = Project::findOrFail($request->project_id);
        $project->project_manager_id = $request->project_manager_id;
        $project->save();

        $manager = User::find($request->project_manager_id);

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
            ]
        ], 200);
    }

    /**
     * GET /api/v1/project/list/assigned
     * Get projects assigned to current employee
     */
    public function listAssignedProjects(Request $request)
    {
        $user = Auth::user();

        $assignments = ProjectAssignment::with(['project.client', 'project.projectManager'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        // Also include user's primary project
        $primaryProject = $user->project;

        return response()->json([
            'status' => 'success',
            'data' => [
                'primary_project' => $primaryProject,
                'assigned_projects' => $assignments->map(function($assignment) {
                    return [
                        'project' => $assignment->project,
                        'role' => $assignment->role,
                        'allocation' => $assignment->allocation_percentage,
                        'assigned_date' => $assignment->assigned_date,
                    ];
                }),
            ]
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