<?php

namespace App\Livewire\Director;

use App\Support\Api;
use Livewire\Component;

class LeaveConfigPage extends Component
{
    public bool $loading = true;
    public bool $saving = false;
    public ?string $error = null;
    public ?string $flash = null;

    public array $configs = [];

    public bool $showEdit = false;
    public ?string $editingType = null;
    public array $form = [];

    public bool $showReset = false;
    public ?int $resetUserId = null;
    public string $resetUserName = '';
    public array $resetForm = [];

    public array $employees = [];
    public bool $employeesLoading = false;
    public string $employeeSearch = '';

    public function mount(): void
    {
        $this->load();
        $this->loadEmployees();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $response = Api::get('/admin/leave-config');

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load leave configuration.';
            $this->loading = false;
            return;
        }

        $this->configs = $response['data'] ?? [];
        $this->loading = false;
    }

    public function loadEmployees(): void
    {
        $this->employeesLoading = true;

        $params = ['per_page' => 100, 'is_active' => 1];

        if ($this->employeeSearch !== '') {
            $params['search'] = $this->employeeSearch;
        }

        $response = Api::get('/employee/list', $params);

        $this->employees = $response['ok'] ? ($response['data']['data'] ?? []) : [];
        $this->employeesLoading = false;
    }

    public function updatedEmployeeSearch(): void
    {
        $this->loadEmployees();
    }

    public function openEdit(string $leaveType): void
    {
        $config = collect($this->configs)->firstWhere('leave_type', $leaveType);

        if (!$config) {
            $this->error = 'Config not found.';
            return;
        }

        $this->editingType = $leaveType;
        $this->form = [
            'leave_type' => $leaveType,
            'default_entitlement' => (float) ($config['default_entitlement'] ?? 0),
            'accrual_rate' => (float) ($config['accrual_rate'] ?? 0),
            'accrual_frequency' => $config['accrual_frequency'] ?? 'monthly',
            'max_carryover' => (float) ($config['max_carryover'] ?? 0),
            'min_service_months' => (float) ($config['min_service_months'] ?? 0),
            'approval_type' => $config['approval_type'] ?? 'manual',
            'auto_approve_max_days' => (int) ($config['auto_approve_max_days'] ?? 0),
            'partial_approve_threshold' => (int) ($config['partial_approve_threshold'] ?? 0),
            'requires_proof_after' => (int) ($config['requires_proof_after'] ?? 0),
            'proof_upload_deadline_days' => (int) ($config['proof_upload_deadline_days'] ?? 3),
            'notification_frequency_hours' => (int) ($config['notification_frequency_hours'] ?? 3),
            'notification_start_hour' => (int) ($config['notification_start_hour'] ?? 8),
            'notification_end_hour' => (int) ($config['notification_end_hour'] ?? 17),
            'convert_to_unpaid_after_deadline' => (bool) ($config['convert_to_unpaid_after_deadline'] ?? false),
            'is_infinite_balance' => (bool) ($config['is_infinite_balance'] ?? false),
            'is_accruable' => (bool) ($config['is_accruable'] ?? true),
            'is_carryover_allowed' => (bool) ($config['is_carryover_allowed'] ?? false),
            'requires_attachment' => (bool) ($config['requires_attachment'] ?? false),
            'min_days_attachment' => (int) ($config['min_days_attachment'] ?? 0),
            'auto_approve' => (bool) ($config['auto_approve'] ?? false),
            'description' => $config['description'] ?? '',
        ];

        $this->error = null;
        $this->showEdit = true;
    }

    public function closeEdit(): void
    {
        $this->showEdit = false;
        $this->editingType = null;
        $this->form = [];
    }

    public function save(): void
    {
        $this->error = null;
        $this->flash = null;

        $payload = [
            'configs' => [
                $this->form,
            ],
        ];

        $this->saving = true;

        $response = Api::put('/admin/leave-config/update', $payload);

        $this->saving = false;

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to save config.');
            return;
        }

        $this->flash = 'Leave configuration saved.';
        $this->closeEdit();
        $this->load();
    }

    public function openReset(int $userId, string $name): void
    {
        $this->resetUserId = $userId;
        $this->resetUserName = $name;
        $this->resetForm = [
            'annual_balance' => '',
            'sick_balance' => '',
            'family_balance' => '',
            'reason' => '',
        ];
        $this->error = null;
        $this->showReset = true;
    }

    public function closeReset(): void
    {
        $this->showReset = false;
        $this->resetUserId = null;
        $this->resetUserName = '';
        $this->resetForm = [];
    }

    public function submitReset(): void
    {
        $this->error = null;
        $this->flash = null;

        $annual = $this->resetForm['annual_balance'];
        $sick = $this->resetForm['sick_balance'];
        $family = $this->resetForm['family_balance'];

        if ($annual === '' && $sick === '' && $family === '') {
            $this->error = 'Provide at least one balance to reset.';
            return;
        }

        if (trim($this->resetForm['reason']) === '') {
            $this->error = 'A reason is required for the audit trail.';
            return;
        }

        $payload = ['reason' => $this->resetForm['reason']];

        if ($annual !== '') {
            $payload['annual_balance'] = (float) $annual;
        }
        if ($sick !== '') {
            $payload['sick_balance'] = (float) $sick;
        }
        if ($family !== '') {
            $payload['family_balance'] = (float) $family;
        }

        $response = Api::post('/admin/leave-config/reset-employee/' . $this->resetUserId, $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to reset balance.');
            return;
        }

        $this->flash = 'Balance reset for ' . $this->resetUserName . '.';
        $this->closeReset();
    }

    public static function approvalTone(string $type): string
    {
        return match ($type) {
            'auto'   => 'success',
            'hybrid' => 'gold',
            'manual' => 'blue',
            default  => 'muted',
        };
    }

    public static function leaveTypeIcon(string $type): string
    {
        return match ($type) {
            'annual'    => 'fa-solid fa-umbrella-beach',
            'sick'      => 'fa-solid fa-thermometer-half',
            'family'    => 'fa-solid fa-users',
            'unpaid'    => 'fa-solid fa-coins',
            'study'     => 'fa-solid fa-graduation-cap',
            'maternity' => 'fa-solid fa-baby',
            'paternity' => 'fa-solid fa-person-breastfeeding',
            default     => 'fa-solid fa-calendar',
        };
    }

    public function render()
    {
        return view('livewire.director.leave-config-page')
            ->layout('layouts.portal', ['title' => 'Leave Config', 'breadcrumb' => 'Leave Config']);
    }
}