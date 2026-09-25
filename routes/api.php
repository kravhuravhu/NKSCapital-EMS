<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\EmployeeTypeController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\ProjectController;
use App\Http\Controllers\API\PSTimesheetController;
use App\Http\Controllers\API\PRTimesheetController;
use App\Http\Controllers\API\TimesheetReportController;
use App\Http\Controllers\API\WorkflowController;
use App\Http\Controllers\API\LeaveController;
use App\Http\Controllers\API\LeaveConfigController;
use App\Http\Controllers\API\LeaveAttachmentController;
use App\Http\Controllers\API\AssetController;
use App\Http\Controllers\API\ContractController;
use App\Http\Controllers\API\RecruitmentController;
use App\Http\Controllers\API\OfferController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\AuditController;
use App\Http\Controllers\API\MeetingController;
use App\Http\Controllers\API\DelegationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/meeting/checkin/{secret}', function ($secret) {
    return redirect(config('app.frontend_url', '/') . '/meetings/checkin/' . $secret);
})->name('meeting.checkin.redirect');

/*
|--------------------------------------------------------------------------
| API Routes - M1: Authentication & RBAC
|--------------------------------------------------------------------------
*/

// No authentication required
Route::prefix('v1/auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('password/reset', [AuthController::class, 'resetPassword']);
    Route::post('2fa/login-verify', [AuthController::class, 'loginVerify2FA']);
});

// Authentication required
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('permissions', [AuthController::class, 'permissions']);
        
        // 2FA routes (require password verification)
        Route::middleware(['password.confirm'])->group(function () {
            Route::post('2fa/enable', [AuthController::class, 'enable2FA']);
            Route::post('2fa/verify', [AuthController::class, 'verify2FA']);
            Route::post('2fa/disable', [AuthController::class, 'disable2FA']);
        });
    });

    // ========================================
    // M2: Employee Foundation & Configuration
    // ========================================

    // Employee Type & Service Type APIs
    Route::prefix('employee')->group(function () {
        Route::post('type/assign', [EmployeeTypeController::class, 'assignEmployeeType']);
        Route::get('type/{id}', [EmployeeTypeController::class, 'getEmployeeType']);
        Route::post('client/assign', [EmployeeController::class, 'assignClient']);
        Route::post('project/assign', [EmployeeController::class, 'assignProject']);
        Route::post('role/assign', [EmployeeController::class, 'assignRoleToUser'])->middleware('role:admin,super_admin');
    });

    // Admin only - Employee Type Management
    Route::prefix('admin')->middleware(['role:admin,super_admin'])->group(function () {
        // Employee Types
        Route::get('employee-type/list', [EmployeeTypeController::class, 'listEmployeeTypes']);
        Route::post('employee-type/create', [EmployeeTypeController::class, 'createEmployeeType']);
        Route::get('service-type/list', [EmployeeTypeController::class, 'listServiceTypes']);
        Route::post('workflow/assign', [EmployeeTypeController::class, 'assignWorkflow']);
    });

    // Employee Management
    Route::prefix('employee')->middleware(['auth:sanctum'])->group(function () {
        Route::post('create', [EmployeeController::class, 'createEmployee'])->middleware('role:admin,super_admin');
        Route::get('profile/{id}', [EmployeeController::class, 'getProfile']);
        Route::put('update', [EmployeeController::class, 'updateProfile']);
        Route::put('update-full/{id}', [EmployeeController::class, 'updateFullProfile'])->middleware('role:admin,manager,director');
        Route::post('deactivate/{id}', [EmployeeController::class, 'deactivateEmployee'])->middleware('role:admin,super_admin');
        Route::post('upload-photo', [EmployeeController::class, 'uploadPhoto']);
        Route::get('list', [EmployeeController::class, 'listEmployees']);
        Route::get('history/{id}', [EmployeeController::class, 'getHistory']);
    });

    // ========================================
    // M3: Client & Project Management
    // ========================================

    // Client Management
    Route::prefix('client')->group(function () {
        Route::get('list', [ClientController::class, 'listClients']);
        Route::post('create', [ClientController::class, 'createClient']);
        Route::put('update/{id}', [ClientController::class, 'updateClient']);
        Route::post('deactivate/{id}', [ClientController::class, 'deactivateClient']);
        Route::post('manager/add', [ClientController::class, 'addClientManager']);
        Route::get('manager/list/{clientId}', [ClientController::class, 'listClientManagers']);
        Route::post('template/upload', [ClientController::class, 'uploadClientTemplate']);
        Route::get('template/{clientId}', [ClientController::class, 'getClientTemplate']);
        Route::put('template/update/{id}', [ClientController::class, 'updateClientTemplate']);
    });

    // Project Management
    Route::prefix('project')->group(function () {
        Route::get('list', [ProjectController::class, 'listProjects']);
        Route::get('list/assigned', [ProjectController::class, 'listAssignedProjects']);
        Route::get('{id}', [ProjectController::class, 'getProject']);
        Route::get('hours/{id}', [ProjectController::class, 'getProjectHours']);
        Route::post('create', [ProjectController::class, 'createProject']);
        Route::put('update/{id}', [ProjectController::class, 'updateProject']);
        Route::post('archive/{id}', [ProjectController::class, 'archiveProject']);
        Route::post('assign-employee', [ProjectController::class, 'assignEmployee']);
        Route::post('remove-employee', [ProjectController::class, 'removeEmployee']);
        Route::post('assign-manager', [ProjectController::class, 'assignProjectManager']);
    });

    // ========================================
    // M4: PS & PR Timesheet Core Workflows
    // ========================================

    // PS Timesheet Routes
    Route::prefix('timesheet/ps')->group(function () {
        Route::post('create', [PSTimesheetController::class, 'create']);
        Route::post('generate-template', [PSTimesheetController::class, 'generateTemplate']);
        Route::get('download-template', [PSTimesheetController::class, 'downloadTemplate']);
        Route::post('upload-signed', [PSTimesheetController::class, 'uploadSigned']);
        Route::post('send-to-client', [PSTimesheetController::class, 'sendToClient']);
        Route::post('recall', [PSTimesheetController::class, 'recall']);
        Route::get('history', [PSTimesheetController::class, 'history']);
        Route::get('{id}', [PSTimesheetController::class, 'show']);
    });

    // PR Timesheet Routes
    Route::prefix('timesheet/pr')->group(function () {
        Route::post('create', [PRTimesheetController::class, 'create']);
        Route::put('save-draft', [PRTimesheetController::class, 'saveDraft']);
        Route::post('submit', [PRTimesheetController::class, 'submit']);
        Route::post('approve/l1', [PRTimesheetController::class, 'approveL1']);
        Route::post('reject/l1', [PRTimesheetController::class, 'rejectL1']);
        Route::post('approve/l2', [PRTimesheetController::class, 'approveL2']);
        Route::post('reject/l2', [PRTimesheetController::class, 'rejectL2']);
        Route::post('recall', [PRTimesheetController::class, 'recall']);
        Route::get('pending/l1', [PRTimesheetController::class, 'pendingL1']);
        Route::get('pending/l2', [PRTimesheetController::class, 'pendingL2']);
        Route::get('list', [PRTimesheetController::class, 'list']);
        Route::get('history', [PRTimesheetController::class, 'history']);
        Route::get('{id}', [PRTimesheetController::class, 'show']);
    });
    
    // ========================================
    // M5: Timesheet Reporting & Payroll Integration
    // ========================================

    // Timesheet Reports
    Route::prefix('timesheet')->group(function () {
        // PR Payroll Export
        Route::get('pr/export/payroll', [TimesheetReportController::class, 'exportPayroll']);
        Route::get('pr/report/monthly', [TimesheetReportController::class, 'monthlyReport']);
        
        // History (Admin/Director view)
        Route::get('ps/history', [TimesheetReportController::class, 'psHistory']);
        Route::get('pr/history', [TimesheetReportController::class, 'prHistory']);
        
        // Reports
        Route::get('report/compliance', [TimesheetReportController::class, 'complianceReport']);
        Route::get('report/department', [TimesheetReportController::class, 'departmentReport']);
        
        // Export endpoints
        Route::post('report/export/excel', [TimesheetReportController::class, 'exportExcel']);
        Route::post('report/export/pdf', [TimesheetReportController::class, 'exportPdf']);
    });

    // Workflow Management (Admin/Director)
    Route::prefix('admin/workflow')->middleware(['role:admin,super_admin,director'])->group(function () {
        Route::get('rules', [WorkflowController::class, 'getRules']);
        Route::get('{type}', [WorkflowController::class, 'getWorkflow']);
        Route::post('config', [WorkflowController::class, 'configureWorkflow']);
        Route::post('assign', [WorkflowController::class, 'assignWorkflow']);
        Route::post('test/{employeeId}', [WorkflowController::class, 'testWorkflow']);
        Route::put('update/{id}', [WorkflowController::class, 'updateWorkflow']);
    });

    // ========================================
    // M6: Leave Management
    // ========================================

    // Leave Routes (all authenticated users)
    Route::prefix('leave')->group(function () {
        Route::post('apply', [LeaveController::class, 'apply']);
        Route::get('balance', [LeaveController::class, 'balance']);
        Route::get('history', [LeaveController::class, 'history']);
        Route::get('calendar', [LeaveController::class, 'calendar']);
        Route::get('conflicts/{dateRange}', [LeaveController::class, 'conflicts']);
        
        // Approval actions (Manager/Director)
        Route::middleware(['role:manager,director,admin,super_admin'])->group(function () {
            Route::get('pending', [LeaveController::class, 'pending']);
            Route::post('approve', [LeaveController::class, 'approve']);
            Route::post('reject', [LeaveController::class, 'reject']);
            Route::post('hold', [LeaveController::class, 'hold']);
            Route::post('partial-approve', [LeaveController::class, 'partialApprove']);
            Route::get('team-report', [LeaveController::class, 'teamReport']);
        });

        // Attachment / proof endpoints
        Route::post('{leaveRequestId}/upload-attachment', [LeaveAttachmentController::class, 'upload']);
        Route::post('{leaveRequestId}/approve-proof', [LeaveAttachmentController::class, 'approveProof'])
            ->middleware(['role:manager,director,admin,super_admin']);
        Route::get('{leaveRequestId}/proof-status', [LeaveAttachmentController::class, 'status']);
        
        // Admin/Director only
        Route::middleware(['role:admin,director,super_admin'])->group(function () {
            Route::post('balance/adjust', [LeaveController::class, 'adjustBalance']);
        });
        
        // Employee cancel
        Route::post('cancel', [LeaveController::class, 'cancel']);
    });

    // Leave Configuration (Super Admin & Director ONLY)
    Route::prefix('admin/leave-config')
        ->middleware(['role:super_admin,director'])
        ->group(function () {
            Route::get('', [LeaveConfigController::class, 'index']);
            Route::put('update', [LeaveConfigController::class, 'update']);
            Route::post('accrual-rate', [LeaveConfigController::class, 'updateAccrualRate']);
            Route::put('entitlements', [LeaveConfigController::class, 'updateEntitlements']);
            Route::post('reset-employee/{userId}', [LeaveConfigController::class, 'resetEmployee']);
        });

    // ========================================
    // M7: Asset Tracking
    // ========================================
    Route::prefix('asset')->group(function () {
        Route::post('register', [AssetController::class, 'register']);
        Route::post('loan', [AssetController::class, 'loan']);
        Route::post('return', [AssetController::class, 'returnAsset']);
        Route::get('overdue', [AssetController::class, 'overdue']);
        Route::get('track', [AssetController::class, 'track']);
        Route::post('repair/request', [AssetController::class, 'requestRepair']);
        Route::get('history/{assetId}', [AssetController::class, 'history']);
        Route::get('report', [AssetController::class, 'report']);
    });

    // ========================================
    // M7(Extended): Contract Management
    // ========================================
    Route::prefix('contract')->group(function () {
        Route::post('upload', [ContractController::class, 'upload']);
        Route::get('view/{id}', [ContractController::class, 'view']);
        Route::get('download/{id}', [ContractController::class, 'download']);
        Route::post('renew', [ContractController::class, 'renew']);
        Route::post('status/update', [ContractController::class, 'updateStatus']);
        Route::post('sign/{id}', [ContractController::class, 'sign']);
        Route::get('expiring', [ContractController::class, 'expiring']);
        Route::get('history/{userId}', [ContractController::class, 'history']);
        Route::delete('delete/{id}', [ContractController::class, 'destroy']);
    });

    // ========================================
    // M8: Recruitment — Requisitions, Candidates, Interviews
    // ========================================
    Route::prefix('recruitment')->group(function () {
        // Requisitions
        Route::get('requisition/list', [RecruitmentController::class, 'listRequisitions']);
        Route::post('requisition/create', [RecruitmentController::class, 'createRequisition']);
        Route::post('requisition/approve', [RecruitmentController::class, 'approveRequisition']);
        Route::put('requisition/update/{id}', [RecruitmentController::class, 'updateRequisition']);

        // Candidates
        Route::get('candidate/list', [RecruitmentController::class, 'listCandidates']);
        Route::get('candidate/{id}', [RecruitmentController::class, 'showCandidate']);
        Route::post('candidate/add', [RecruitmentController::class, 'addCandidate']);
        Route::post('candidate/parse-resume', [RecruitmentController::class, 'parseResume']);
        Route::post('candidate/reject', [RecruitmentController::class, 'rejectCandidate']);
        Route::put('candidate/stage', [RecruitmentController::class, 'advanceStage']);

        // Interviews
        Route::get('interview/list', [RecruitmentController::class, 'listInterviews']);
        Route::get('interview/calendar', [RecruitmentController::class, 'interviewCalendar']);
        Route::get('interview/{id}', [RecruitmentController::class, 'showInterview']);
        Route::post('interview/schedule', [RecruitmentController::class, 'scheduleInterview']);
        Route::post('interview/feedback', [RecruitmentController::class, 'submitFeedback']);
        Route::post('interview/cancel/{id}', [RecruitmentController::class, 'cancelInterview']);
        Route::put('interview/reschedule/{id}', [RecruitmentController::class, 'rescheduleInterview']);

        // Offers
        Route::post('offer/generate', [OfferController::class, 'generate']);
        Route::get('offer/{id}', [OfferController::class, 'show']);
        Route::post('offer/accept', [OfferController::class, 'accept']);
        Route::post('offer/decline', [OfferController::class, 'decline']);
        Route::post('offer/send-email', [OfferController::class, 'sendEmail']);
        Route::post('convert-to-employee', [OfferController::class, 'convertToEmployee']);

        // Analytics
        Route::get('metrics', [OfferController::class, 'metrics']);
        Route::get('report', [OfferController::class, 'report']);
    });

    // ========================================
    // M11: Notifications
    // ========================================
    Route::prefix('notifications')->group(function () {
        Route::get('list', [NotificationController::class, 'list']);
        Route::get('unread/count', [NotificationController::class, 'unreadCount']);
        Route::post('mark-read', [NotificationController::class, 'markRead']);
        Route::post('mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::delete('delete/{id}', [NotificationController::class, 'delete']);
        Route::get('preferences', [NotificationController::class, 'preferences']);
        Route::put('preferences/update', [NotificationController::class, 'updatePreferences']);
        Route::post('send-email', [NotificationController::class, 'sendEmail'])
            ->middleware(['role:admin,super_admin,director']);
    });

    // ========================================
    // M11: Audit & Compliance
    // ========================================
    Route::prefix('admin/audit')->middleware(['role:admin,super_admin,director'])->group(function () {
        Route::post('search', [AuditController::class, 'search']);
        Route::get('export', [AuditController::class, 'export']);
        Route::get('verify-chain', [AuditController::class, 'verifyChain']);
        Route::get('report', [AuditController::class, 'report']);
        Route::get('user/{userId}', [AuditController::class, 'userAudit']);
        Route::get('table/{tableName}', [AuditController::class, 'tableAudit']);
    // M10: Meeting & Attendance
    // ========================================
    Route::prefix('meeting')->group(function () {
        Route::post('schedule', [MeetingController::class, 'schedule']);
        Route::get('list', [MeetingController::class, 'list']);
        Route::get('qr/{meetingId}', [MeetingController::class, 'qr']);
        Route::get('ical/{meetingId}', [MeetingController::class, 'ical']);
        Route::post('checkin', [MeetingController::class, 'checkin']);
        Route::post('override', [MeetingController::class, 'override']);
        Route::post('cancel/{id}', [MeetingController::class, 'cancel']);
        Route::put('update/{id}', [MeetingController::class, 'update']);
        Route::get('attendance/{meetingId}', [MeetingController::class, 'attendance']);
        Route::get('late-report', [MeetingController::class, 'lateReport']);
    });

    // ========================================
    // M10: Delegation
    // ========================================
    Route::prefix('admin/delegation')->group(function () {
        Route::get('list', [DelegationController::class, 'list']);
        Route::get('{id}', [DelegationController::class, 'show']);
        Route::post('create', [DelegationController::class, 'create']);
        Route::put('update/{id}', [DelegationController::class, 'update']);
        Route::post('activate/{id}', [DelegationController::class, 'activate']);
        Route::post('revoke', [DelegationController::class, 'revoke']);
    });

    // Role management - Admin only
    Route::prefix('admin/roles')
        ->middleware(['role:admin,super_admin'])
        ->group(function () {
            Route::post('create', [RoleController::class, 'createRole']);
            Route::get('list', [RoleController::class, 'listRoles']);
            Route::put('update/{id}', [RoleController::class, 'updateRole']);
            Route::delete('delete/{id}', [RoleController::class, 'deleteRole']);
            Route::post('assign/{userId}', [RoleController::class, 'assignRole']);
            Route::post('remove/{userId}', [RoleController::class, 'removeRole']);
            Route::get('permissions/{roleId}', [RoleController::class, 'getRolePermissions']);
            Route::post('permissions/assign', [RoleController::class, 'assignPermission']);
        });
});