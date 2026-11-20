<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Support\Api;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;
use App\Livewire\Concerns\HasFlashMessages;

class SupportConsole extends Component
{
    use HasFlashMessages;
    
    public bool $loading = false;
    public ?string $error = null;
    public ?string $flash = null;

    public string $query = '';
    public array $results = [];
    public bool $hasSearched = false;

    public ?array $selectedUser = null;
    public array $sessions = [];
    public array $auditSlice = [];

    public array $managers = [];
    public array $clients = [];
    public array $projects = [];

    public bool $showImpersonate = false;
    public string $impersonatePassword = '';

    public bool $showForceLogout = false;
    public string $forceLogoutPassword = '';

    public bool $showReset2fa = false;
    public string $reset2faPassword = '';

    public bool $showEmployeeNumber = false;
    public string $newEmployeeNumber = '';
    public string $employeeNumberPassword = '';

    public bool $showRelink = false;
    public array $relinkForm = [];
    public string $relinkPassword = '';

    public bool $showCompanyEmail = false;
    public string $companyEmailInput = '';
    public bool $updateLoginEmail = false;
    public string $companyEmailPassword = '';

    public function mount(): void
    {
        // load() fires from wire:init.
    }

    public function load(): void
    {
        // picker data fetched lazily when a user is selected.
    }

    public function search(): void
    {
        $this->error = null;
        $this->results = [];
        $this->hasSearched = true;
        $this->selectedUser = null;

        if (strlen(trim($this->query)) < 2) {
            $this->error = 'Enter at least 2 characters.';
            return;
        }

        $this->loading = true;

        $response = Api::post('/admin/support/search', ['q' => $this->query]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Search failed.';
            $this->loading = false;
            return;
        }

        $this->results = $response['data']['users'] ?? [];
        $this->loading = false;
    }

    public function selectUser(int $id): void
    {
        $this->error = null;
        $this->loading = true;

        $response = Api::get('/admin/support/users/' . $id);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load user.';
            $this->loading = false;
            return;
        }

        $this->selectedUser = $response['data']['user'] ?? null;
        $this->sessions = $response['data']['sessions'] ?? [];
        $this->auditSlice = $response['data']['audit_slice'] ?? [];

        $this->relinkForm = [
            'manager_id' => $this->selectedUser['manager_id'] ?? '',
            'client_id' => $this->selectedUser['client_id'] ?? '',
            'project_id' => $this->selectedUser['project_id'] ?? '',
        ];

        $this->loadPickerData();
        $this->loading = false;
    }

    public function back(): void
    {
        $this->selectedUser = null;
        $this->sessions = [];
        $this->auditSlice = [];
    }

    protected function loadPickerData(): void
    {
        if (empty($this->managers)) {
            $m = Api::get('/employee/list', ['per_page' => 300, 'is_active' => 1]);
            if ($m['ok']) {
                $all = $m['data']['data'] ?? [];
                $this->managers = array_values(array_filter(
                    $all,
                    fn ($u) => in_array($u['role'] ?? '', ['manager', 'director', 'operational_manager', 'admin'], true)
                ));
            }
        }

        if (empty($this->clients)) {
            $c = Api::get('/client/list', ['per_page' => 300, 'is_active' => 1]);
            if ($c['ok']) {
                $this->clients = $c['data']['data'] ?? [];
            }
        }

        if (empty($this->projects)) {
            $p = Api::get('/project/list', ['per_page' => 300]);
            if ($p['ok']) {
                $this->projects = $p['data']['data'] ?? [];
            }
        }
    }

    // === impersonate ===

    public function openImpersonate(): void
    {
        $this->showImpersonate = true;
        $this->impersonatePassword = '';
        $this->error = null;
    }

    public function closeImpersonate(): void
    {
        $this->showImpersonate = false;
        $this->impersonatePassword = '';
    }

    public function confirmImpersonate(): void
    {
        $this->error = null;

        if (empty($this->impersonatePassword)) {
            $this->error = 'Enter your admin password.';
            return;
        }

        if (! Auth::validate(['email' => auth()->user()->email, 'password' => $this->impersonatePassword])) {
            $this->error = 'Password is incorrect.';
            return;
        }

        if (! $this->selectedUser) {
            $this->error = 'No user selected.';
            return;
        }

        $response = Api::post('/admin/support/users/' . $this->selectedUser['id'] . '/impersonate', [
            'password' => $this->impersonatePassword,
        ]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed to impersonate.';
            return;
        }

        $token = $response['data']['impersonation_token'] ?? null;
        $targetUser = $response['data']['target_user'] ?? null;

        if (! $token || ! $targetUser) {
            $this->error = 'Impersonation response was incomplete.';
            return;
        }

        Session::put('impersonation_admin_user_id', auth()->id());
        Session::put('impersonation_admin_token', Session::get('api_token'));
        Session::put('api_token', $token);
        Session::put('impersonated_by_user_id', auth()->id());

        $userModel = User::find($targetUser['id']);
        if ($userModel) {
            Auth::login($userModel);
        }

        $this->closeImpersonate();
        $this->redirect(route('portal.dashboard'));
    }

    // === force logout ===

    public function openForceLogout(): void
    {
        $this->showForceLogout = true;
        $this->forceLogoutPassword = '';
        $this->error = null;
    }

    public function closeForceLogout(): void
    {
        $this->showForceLogout = false;
        $this->forceLogoutPassword = '';
    }

    public function confirmForceLogout(): void
    {
        $this->error = null;

        if (empty($this->forceLogoutPassword)) {
            $this->error = 'Enter your admin password.';
            return;
        }

        if (! Auth::validate(['email' => auth()->user()->email, 'password' => $this->forceLogoutPassword])) {
            $this->error = 'Password is incorrect.';
            return;
        }

        $response = Api::post('/admin/support/users/' . $this->selectedUser['id'] . '/force-logout', [
            'password' => $this->forceLogoutPassword,
        ]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed.';
            return;
        }

        $this->flash = 'All sessions terminated.';
        $this->closeForceLogout();
        $this->selectUser($this->selectedUser['id']);
    }

    // === reset 2FA ===

    public function openReset2fa(): void
    {
        $this->showReset2fa = true;
        $this->reset2faPassword = '';
        $this->error = null;
    }

    public function closeReset2fa(): void
    {
        $this->showReset2fa = false;
        $this->reset2faPassword = '';
    }

    public function confirmReset2fa(): void
    {
        $this->error = null;

        if (empty($this->reset2faPassword)) {
            $this->error = 'Enter your admin password.';
            return;
        }

        if (! Auth::validate(['email' => auth()->user()->email, 'password' => $this->reset2faPassword])) {
            $this->error = 'Password is incorrect.';
            return;
        }

        $response = Api::post('/admin/support/users/' . $this->selectedUser['id'] . '/reset-2fa', [
            'password' => $this->reset2faPassword,
        ]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed.';
            return;
        }

        $this->flash = '2FA reset.';
        $this->closeReset2fa();
        $this->selectUser($this->selectedUser['id']);
    }

    // === employee number ===

    public function openEmployeeNumber(): void
    {
        $this->showEmployeeNumber = true;
        $this->newEmployeeNumber = $this->selectedUser['employee_number'] ?? '';
        $this->employeeNumberPassword = '';
        $this->error = null;
    }

    public function closeEmployeeNumber(): void
    {
        $this->showEmployeeNumber = false;
        $this->newEmployeeNumber = '';
        $this->employeeNumberPassword = '';
    }

    public function confirmEmployeeNumber(): void
    {
        $this->error = null;

        if (empty($this->employeeNumberPassword)) {
            $this->error = 'Enter your admin password.';
            return;
        }

        if (empty($this->newEmployeeNumber)) {
            $this->error = 'Enter the new employee number.';
            return;
        }

        $response = Api::post('/admin/support/users/' . $this->selectedUser['id'] . '/employee-number', [
            'password' => $this->employeeNumberPassword,
            'employee_number' => $this->newEmployeeNumber,
        ]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed.');
            return;
        }

        $this->flash = 'Employee number updated.';
        $this->closeEmployeeNumber();
        $this->selectUser($this->selectedUser['id']);
    }

    // === relink ===

    public function openRelink(): void
    {
        $this->showRelink = true;
        $this->relinkPassword = '';
        $this->error = null;
    }

    public function closeRelink(): void
    {
        $this->showRelink = false;
        $this->relinkPassword = '';
    }

    public function confirmRelink(): void
    {
        $this->error = null;

        if (empty($this->relinkPassword)) {
            $this->error = 'Enter your admin password.';
            return;
        }

        $response = Api::post('/admin/support/users/' . $this->selectedUser['id'] . '/relink', [
            'password' => $this->relinkPassword,
            'manager_id' => $this->relinkForm['manager_id'] ?: null,
            'client_id' => $this->relinkForm['client_id'] ?: null,
            'project_id' => $this->relinkForm['project_id'] ?: null,
        ]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed.');
            return;
        }

        $this->flash = 'Relationships updated.';
        $this->closeRelink();
        $this->selectUser($this->selectedUser['id']);
    }

    public function render()
    {
        return view('livewire.admin.support-console')
            ->layout('layouts.portal', ['title' => 'Support Console', 'breadcrumb' => 'Support Console']);
    }

    public function openCompanyEmail(): void
    {
        $this->showCompanyEmail = true;
        $this->companyEmailInput = $this->selectedUser['company_email'] ?? '';
        $this->updateLoginEmail = (bool) ($this->selectedUser['pending_company_email'] ?? false);
        $this->companyEmailPassword = '';
        $this->error = null;
    }

    public function closeCompanyEmail(): void
    {
        $this->showCompanyEmail = false;
        $this->companyEmailInput = '';
        $this->updateLoginEmail = false;
        $this->companyEmailPassword = '';
    }

    public function confirmCompanyEmail(): void
    {
        $this->error = null;

        if (empty($this->companyEmailPassword)) {
            $this->error = 'Enter your admin password.';
            return;
        }

        if (empty($this->companyEmailInput)) {
            $this->error = 'Enter the company email.';
            return;
        }

        $response = Api::post('/admin/support/users/' . $this->selectedUser['id'] . '/assign-company-email', [
            'password' => $this->companyEmailPassword,
            'company_email' => $this->companyEmailInput,
            'update_login_email' => $this->updateLoginEmail ? 1 : 0,
        ]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed.');
            return;
        }

        $this->flash = $response['data']['temporary_password']
            ? 'Company email assigned. New temporary password: ' . $response['data']['temporary_password']
            : 'Company email assigned.';

        $this->closeCompanyEmail();
        $this->selectUser($this->selectedUser['id']);
    }
}