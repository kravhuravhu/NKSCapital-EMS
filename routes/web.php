<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Shared\SmokeTest;
use App\Livewire\Employee\Dashboard as EmployeeDashboard;
use App\Livewire\Manager\Dashboard  as ManagerDashboard;
use App\Livewire\Director\Dashboard as DirectorDashboard;
use App\Livewire\Admin\Dashboard    as AdminDashboard;
use App\Livewire\Recruiter\Dashboard as RecruiterDashboard;
use App\Livewire\Employee\Timesheet;
use App\Livewire\Employee\Leave;
use App\Livewire\Employee\Assets;
use App\Livewire\Employee\Documents;
use App\Http\Controllers\API\DocumentController;
use App\Livewire\Employee\Projects;
use App\Livewire\Employee\Profile;
use App\Livewire\Employee\Security;
use App\Livewire\Employee\Notifications;
use App\Livewire\Manager\PendingL1;
use App\Livewire\Manager\TeamTimesheets;
use App\Livewire\Manager\PendingLeave;
use App\Livewire\Manager\TeamCalendar;
use App\Livewire\Manager\TeamAssets;
use App\Livewire\Manager\Meetings;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('portal.dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth'])->get('/dashboard', function () {
    return redirect()->route('portal.dashboard');
})->name('dashboard');

Route::middleware(['auth'])->prefix('portal/documents')->name('portal.documents.')->group(function () {
    Route::get('/{type}/{id}', [DocumentController::class, 'stream'])
        ->where('type', 'document|contract')
        ->name('stream');
});

Route::middleware(['auth', 'api.token'])->prefix('portal')->name('portal.')->group(function () {

    Route::get('/dashboard', function () {
        return redirect()->route('portal.' . auth()->user()->role . '.dashboard');
    })->name('dashboard');

    Route::get('/smoke-test', SmokeTest::class)->name('smoke-test');

    // employee
    Route::middleware('role:employee,manager,director,admin,super_admin')
        ->prefix('employee')->name('employee.')->group(function () {
            Route::get('/dashboard', EmployeeDashboard::class)->name('dashboard');
            Route::get('/timesheet', Timesheet::class)->name('timesheet');
            Route::get('/projects', Projects::class)->name('projects');
            Route::get('/leave', Leave::class)->name('leave');
            Route::get('/assets', Assets::class)->name('assets');
            Route::get('/documents', Documents::class)->name('documents');
            Route::get('/profile', Profile::class)->name('profile');
            Route::get('/security', Security::class)->name('security');
            Route::get('/notifications', Notifications::class)->name('notifications');
        });

    // manager
    Route::middleware('role:manager,director,admin,super_admin')
        ->prefix('manager')->name('manager.')->group(function () {
            Route::get('/dashboard', ManagerDashboard::class)->name('dashboard');
            Route::get('/pending-l1', PendingL1::class)->name('pending-l1');
            Route::get('/team-timesheets', TeamTimesheets::class)->name('team-timesheets');
            Route::get('/pending-leave', PendingLeave::class)->name('pending-leave');
            Route::get('/team-calendar', TeamCalendar::class)->name('team-calendar');
            Route::get('/team-assets', TeamAssets::class)->name('team-assets');
            Route::get('/meetings', Meetings::class)->name('meetings');
            Route::get('/team-reports',    SmokeTest::class)->name('team-reports');
            Route::get('/projects',        SmokeTest::class)->name('projects');
        });

    // director
    Route::middleware('role:director,admin,super_admin')
        ->prefix('director')->name('director.')->group(function () {
            Route::get('/dashboard',          DirectorDashboard::class)->name('dashboard');
            Route::get('/pending-l2',         SmokeTest::class)->name('pending-l2');
            Route::get('/audit-log',          SmokeTest::class)->name('audit-log');
            Route::get('/delegation',         SmokeTest::class)->name('delegation');
            Route::get('/payroll-export',     SmokeTest::class)->name('payroll-export');
            Route::get('/company-reports',    SmokeTest::class)->name('company-reports');
            Route::get('/override-approvals', SmokeTest::class)->name('override-approvals');
            Route::get('/leave-config',       SmokeTest::class)->name('leave-config');
        });

    // admin
    Route::middleware('role:admin,super_admin')
        ->prefix('admin')->name('admin.')->group(function () {
            Route::get('/dashboard',        AdminDashboard::class)->name('dashboard');
            Route::get('/employees',        SmokeTest::class)->name('employees');
            Route::get('/employee-types',   SmokeTest::class)->name('employee-types');
            Route::get('/roles',            SmokeTest::class)->name('roles');
            Route::get('/clients',          SmokeTest::class)->name('clients');
            Route::get('/client-templates', SmokeTest::class)->name('client-templates');
            Route::get('/projects',         SmokeTest::class)->name('projects');
            Route::get('/assets',           SmokeTest::class)->name('assets');
            Route::get('/contracts',        SmokeTest::class)->name('contracts');
            Route::get('/system-config',    SmokeTest::class)->name('system-config');
            Route::get('/queue-monitor',    SmokeTest::class)->name('queue-monitor');
            Route::get('/backup-restore',   SmokeTest::class)->name('backup-restore');
        });

    // recruiter
    Route::middleware('role:recruiter,manager,director,admin,super_admin')
        ->prefix('recruiter')->name('recruiter.')->group(function () {
            Route::get('/dashboard',    RecruiterDashboard::class)->name('dashboard');
            Route::get('/requisitions', SmokeTest::class)->name('requisitions');
            Route::get('/candidates',   SmokeTest::class)->name('candidates');
            Route::get('/pipeline',     SmokeTest::class)->name('pipeline');
            Route::get('/interviews',   SmokeTest::class)->name('interviews');
            Route::get('/offers',       SmokeTest::class)->name('offers');
            Route::get('/convert',      SmokeTest::class)->name('convert');
            Route::get('/reports',      SmokeTest::class)->name('reports');
        });
});