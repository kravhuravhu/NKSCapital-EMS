<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    /**
     * POST /api/v1/employee/create
     * Admin creates employee profile
     */
    public function createEmployee(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Required fields
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'employee_type' => 'required|in:ps,pr',
            'service_type' => 'required|in:permanent,contractor,temporary,intern',
            'role' => 'required|in:employee,manager,director,admin,recruiter',
            'employee_number' => 'nullable|string|unique:users,employee_number',
            'id_number' => 'nullable|string',
            'phone' => 'nullable|string',
            'department' => 'nullable|string',
            'position' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
            'client_id' => 'nullable|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'hire_date' => 'nullable|date',
            'address' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string',
            'emergency_contact_phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate PS employee has client assigned
        if ($request->employee_type === 'ps' && !$request->client_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'PS employee must have a client assigned'
            ], 422);
        }

        // Validate PR employee has project assigned
        if ($request->employee_type === 'pr' && !$request->project_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'PR employee must have a project assigned'
            ], 422);
        }

        // Generate employee number if not provided
        $employeeNumber = $request->employee_number ?? $this->generateEmployeeNumber();

        // Generate temporary password
        $tempPassword = Str::random(10);

        $user = User::create([
            'employee_number' => $employeeNumber,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($tempPassword),
            'id_number' => $request->id_number,
            'phone' => $request->phone,
            'department' => $request->department,
            'position' => $request->position,
            'role' => $request->role,
            'employee_type' => $request->employee_type,
            'service_type' => $request->service_type,
            'manager_id' => $request->manager_id,
            'client_id' => $request->client_id,
            'project_id' => $request->project_id,
            'hire_date' => $request->hire_date,
            'address' => $request->address,
            'emergency_contact_name' => $request->emergency_contact_name,
            'emergency_contact_phone' => $request->emergency_contact_phone,
            'is_active' => true,
        ]);

        // Assign role using Spatie
        $user->assignRole($request->role);

        // TODO: Send welcome email with temporary password

        return response()->json([
            'status' => 'success',
            'message' => 'Employee created successfully',
            'data' => [
                'user' => $user,
                'temporary_password' => $tempPassword,
                'roles' => $user->getRoleNames(),
            ]
        ], 201);
    }

    /**
     * GET /api/v1/employee/profile/{id}
     * Get employee profile
     */
    public function getProfile($id)
    {
        $user = User::with(['manager', 'client', 'project', 'employeeType', 'serviceType'])
                    ->findOrFail($id);

        // Check permission
        $authUser = Auth::user();
        if ($authUser->id !== $user->id && !in_array($authUser->role, ['admin', 'director', 'manager'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to view this profile'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'employee_number' => $user->employee_number,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'department' => $user->department,
                'position' => $user->position,
                'role' => $user->role,
                'employee_type' => $user->employee_type,
                'employee_type_details' => $user->employeeType,
                'service_type' => $user->service_type,
                'service_type_details' => $user->serviceType,
                'manager' => $user->manager ? $user->manager->full_name : null,
                'manager_id' => $user->manager_id,
                'client' => $user->client ? $user->client->company_name : null,
                'client_id' => $user->client_id,
                'project' => $user->project ? $user->project->name : null,
                'project_id' => $user->project_id,
                'hire_date' => $user->hire_date,
                'leave_balance_annual' => $user->leave_balance_annual,
                'leave_balance_sick' => $user->leave_balance_sick,
                'is_active' => $user->is_active,
                'profile_photo_url' => $user->profile_photo_url,
            ]
        ], 200);
    }

    /**
     * PUT /api/v1/employee/update
     * Employee updates own profile (limited fields)
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string',
            'emergency_contact_phone' => 'nullable|string',
            'profile_photo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only([
            'phone', 'address', 'emergency_contact_name', 
            'emergency_contact_phone', 'profile_photo'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => $user
        ], 200);
    }

    /**
     * PUT /api/v1/employee/update-full/{id}
     * Full profile update (Admin/Manager)
     */
    public function updateFullProfile(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Check permission
        $authUser = Auth::user();
        if (!in_array($authUser->role, ['admin', 'director', 'manager'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to update this profile'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'department' => 'nullable|string',
            'position' => 'nullable|string',
            'role' => 'nullable|in:employee,manager,director,admin,recruiter',
            'employee_type' => 'nullable|in:ps,pr',
            'service_type' => 'nullable|in:permanent,contractor,temporary,intern',
            'manager_id' => 'nullable|exists:users,id',
            'client_id' => 'nullable|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'hire_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->all());

        // If role changed, update Spatie role
        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Employee profile updated successfully',
            'data' => $user
        ], 200);
    }

    /**
     * POST /api/v1/employee/deactivate/{id}
     * Deactivate employee account
     */
    public function deactivateEmployee($id)
    {
        $user = User::findOrFail($id);

        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to deactivate employees'
            ], 403);
        }

        $user->is_active = false;
        $user->termination_date = now();
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Employee deactivated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'is_active' => $user->is_active,
                'termination_date' => $user->termination_date,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/employee/upload-photo
     * Upload profile photo
     */
    public function uploadPhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:255120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $photo = $request->file('photo');
        $filename = time() . '.' . $photo->getClientOriginalExtension();
        $path = $photo->storeAs('profile-photos', $filename, 'public');

        $user->profile_photo = $filename;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Photo uploaded successfully',
            'data' => [
                'profile_photo_url' => $user->profile_photo_url,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/employee/list
     * List employees with filters
     */
    public function listEmployees(Request $request)
    {
        $query = User::query();

        // Filters (as per M2 API spec)
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        if ($request->has('employee_type')) {
            $query->where('employee_type', $request->employee_type);
        }
        if ($request->has('service_type')) {
            $query->where('service_type', $request->service_type);
        }
        if ($request->has('department')) {
            $query->where('department', $request->department);
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('employee_number', 'LIKE', "%{$search}%");
            });
        }

        $users = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => 'success',
            'data' => $users
        ], 200);
    }

    /**
     * GET /api/v1/employee/history/{id}
     * Get employment history
     */
    public function getHistory($id)
    {
        $user = User::with(['contracts', 'leaveRequests', 'projectAssignments'])->findOrFail($id);

        // Check permission
        $authUser = Auth::user();
        if ($authUser->id !== $user->id && !in_array($authUser->role, ['admin', 'director', 'manager'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to view this history'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'employee' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'employee_number' => $user->employee_number,
                ],
                'contracts' => $user->contracts,
                'leave_history' => $user->leaveRequests()->where('status', 'approved')->get(),
                'project_history' => $user->projectAssignments,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/employee/client/assign
     * Assign client to PS employee
     */
    public function assignClient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'client_id' => 'required|exists:clients,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'director', 'manager'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to assign clients'
            ], 403);
        }

        $user = User::findOrFail($request->user_id);
        $user->client_id = $request->client_id;
        $user->employee_type = 'ps';
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Client assigned successfully',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->full_name,
                'client_id' => $user->client_id,
                'client' => $user->client,
                'employee_type' => $user->employee_type,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/employee/project/assign
     * Assign project to PR employee
     */
    public function assignProject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'project_id' => 'required|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'director', 'manager'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to assign projects'
            ], 403);
        }

        $user = User::findOrFail($request->user_id);
        $user->project_id = $request->project_id;
        $user->employee_type = 'pr';
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Project assigned successfully',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->full_name,
                'project_id' => $user->project_id,
                'project' => $user->project,
                'employee_type' => $user->employee_type,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/employee/role/assign
     * Assign role to user (Admin only)
     */
    public function assignRoleToUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check permission
        if (!in_array(Auth::user()->role, ['admin', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to assign roles'
            ], 403);
        }

        $user = User::findOrFail($request->user_id);
        $user->syncRoles([$request->role]);
        $user->role = $request->role;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Role assigned successfully',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->full_name,
                'role' => $user->role,
                'roles' => $user->getRoleNames(),
            ]
        ], 200);
    }

    private function generateEmployeeNumber(): string
    {
        $prefix = 'EN';
        $year = date('y');

        $lastEmployee = User::whereNotNull('employee_number')
            ->orderByDesc('employee_sequence')
            ->first();

        $sequence = $lastEmployee
            ? $lastEmployee->employee_sequence + 1
            : 18;

        // Convert sequence to Base36 and pad to 4 characters
        $encodedSequence = strtoupper(
            str_pad(base_convert($sequence, 10, 36), 4, '0', STR_PAD_LEFT)
        );

        return $prefix . $year . $encodedSequence;
    }
}