<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Livewire\Component;

class SystemConfig extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    // configs keyed by config_key for fast lookup
    public array $configs = [];
    public array $schema = [];
    public array $edits = [];   // config_key => new value (only dirty entries)

    public bool $showSave = false;
    public string $savePassword = '';
    public bool $requiresPassword = false;
    public bool $saving = false;

    // default labels for schema keys
    protected array $labels = [
        'company.name' => 'Company Name',
        'company.registration' => 'Registration Number',
        'company.vat' => 'VAT Number',
        'company.address' => 'Registered Address',
        'company.phone' => 'Company Phone',
        'company.email' => 'Company Email',
        'leave.year_start_month' => 'Leave Year Start (month)',
        'leave.carryover_max_days' => 'Max Carryover Days',
        'timesheet.deadline_day' => 'Timesheet Deadline (day of month)',
        'timesheet.max_regular_hours' => 'Max Regular Hours / Month',
        'timesheet.max_overtime_hours' => 'Max Overtime Hours / Month',
        'payroll.default_hourly_rate' => 'Default Hourly Rate (R)',
        'payroll.overtime_multiplier' => 'Overtime Multiplier',
        'payroll.ps_billing_multiplier' => 'PS Billing Multiplier',
        'payroll.export_day' => 'Payroll Export Day',
        'notifications.email_enabled' => 'Email Notifications Enabled',
        'notifications.digest_hour' => 'Daily Digest Hour (0-23)',
        'notifications.poll_seconds' => 'Notification Poll Interval (seconds)',
        'security.session_timeout_minutes' => 'Session Timeout (minutes)',
        'security.password_reset_ttl_minutes' => 'Password Reset TTL (minutes)',
        'security.max_login_attempts' => 'Max Login Attempts',
    ];

    protected array $sensitiveKeys = [
        'payroll.default_hourly_rate',
        'payroll.overtime_multiplier',
        'payroll.ps_billing_multiplier',
        'security.session_timeout_minutes',
        'security.password_reset_ttl_minutes',
        'security.max_login_attempts',
    ];

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;
        $this->edits = [];

        $response = Api::get('/admin/system-config');

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load configuration.';
            $this->loading = false;
            return;
        }

        $rows = $response['data'] ?? [];
        $schema = $response['schema'] ?? [];

        // ensure every schema key appears, even if the row has not been created yet
        $byKey = collect($rows)->keyBy('config_key')->toArray();

        $merged = [];
        foreach ($schema as $key => $type) {
            $row = $byKey[$key] ?? null;

            $merged[$key] = [
                'config_key' => $key,
                'config_value' => $row['config_value'] ?? $this->defaultForType($type),
                'config_type' => $type,
                'description' => $row['description'] ?? null,
                'label' => $this->labels[$key] ?? $key,
                'category' => explode('.', $key)[0],
                'is_sensitive' => in_array($key, $this->sensitiveKeys, true),
                'exists' => $row !== null,
                'updated_by' => $row['updated_by'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }

        // preserve any extra rows not in the schema (custom keys added manually)
        foreach ($byKey as $key => $row) {
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'config_key' => $key,
                    'config_value' => $row['config_value'] ?? '',
                    'config_type' => $row['config_type'] ?? 'string',
                    'description' => $row['description'] ?? null,
                    'label' => $this->labels[$key] ?? $key,
                    'category' => explode('.', $key)[0] ?? 'general',
                    'is_sensitive' => false,
                    'exists' => true,
                    'updated_by' => $row['updated_by'] ?? null,
                    'updated_at' => $row['updated_at'] ?? null,
                ];
            }
        }

        $this->configs = $merged;
        $this->schema = $schema;
        $this->loading = false;
    }

    protected function defaultForType(string $type): string
    {
        return match ($type) {
            'integer', 'decimal' => '0',
            'boolean' => '0',
            default => '',
        };
    }

    public function updatedEdits($value, $key): void
    {
        // normalise booleans coming from checkboxes
        if (str_ends_with($key, 'email_enabled')) {
            $this->edits[$key] = $value ? '1' : '0';
        }
    }

    public function edit(string $key, $value): void
    {
        $original = $this->configs[$key]['config_value'] ?? '';

        if ((string) $value === (string) $original) {
            unset($this->edits[$key]);
            return;
        }

        $this->edits[$key] = $value;
    }

    public function cancel(): void
    {
        $this->edits = [];
        $this->error = null;
    }

    public function openSave(): void
    {
        $this->error = null;

        if (empty($this->edits)) {
            $this->error = 'No changes to save.';
            return;
        }

        // only require password if any edited key is sensitive
        $touchedSensitive = collect(array_keys($this->edits))
            ->contains(fn ($k) => in_array($k, $this->sensitiveKeys, true));

        $this->requiresPassword = $touchedSensitive;
        $this->savePassword = '';
        $this->showSave = true;
    }

    public function closeSave(): void
    {
        $this->showSave = false;
        $this->savePassword = '';
    }

    public function submitSave(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->edits)) {
            $this->error = 'No changes to save.';
            $this->closeSave();
            return;
        }

        $this->saving = true;

        // re-verify password for sensitive changes
        if ($this->requiresPassword) {
            if (empty($this->savePassword)) {
                $this->error = 'Password is required for sensitive changes.';
                $this->saving = false;
                return;
            }

            $verify = Api::post('/admin/system-config/verify-password', [
                'password' => $this->savePassword,
            ]);

            if (!$verify['ok']) {
                $this->error = $verify['message'] ?? 'Password verification failed.';
                $this->saving = false;
                return;
            }
        }

        $payload = [];
        foreach ($this->edits as $key => $value) {
            $payload[] = [
                'config_key' => $key,
                'config_value' => $value,
            ];
        }

        $response = Api::put('/admin/system-config/update', [
            'configs' => $payload,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = is_array($errs) ? (collect($errs)->flatten()->first() ?? null) : $errs;
            $this->error = $first ?: ($response['message'] ?? 'Failed to save configuration.');
            $this->saving = false;
            return;
        }

        $this->flash = count($payload) . ' setting(s) saved.';
        $this->saving = false;
        $this->closeSave();
        $this->load();
    }

    public function getGroupedConfigsProperty(): array
    {
        $grouped = [];

        foreach ($this->configs as $key => $cfg) {
            $cat = $cfg['category'] ?? 'general';
            $grouped[$cat][] = $cfg;
        }

        // stable order: known categories first
        $order = ['company', 'leave', 'timesheet', 'payroll', 'notifications', 'security'];
        $sorted = [];

        foreach ($order as $cat) {
            if (isset($grouped[$cat])) {
                $sorted[$cat] = $grouped[$cat];
                unset($grouped[$cat]);
            }
        }

        // append any other categories
        foreach ($grouped as $cat => $items) {
            $sorted[$cat] = $items;
        }

        return $sorted;
    }

    public function getDirtyCountProperty(): int
    {
        return count($this->edits);
    }

    public static function categoryIcon(string $category): string
    {
        return match ($category) {
            'company'       => 'fa-solid fa-building',
            'leave'         => 'fa-solid fa-umbrella-beach',
            'timesheet'     => 'fa-solid fa-clipboard-list',
            'payroll'       => 'fa-solid fa-money-check-dollar',
            'notifications' => 'fa-solid fa-bell',
            'security'      => 'fa-solid fa-shield-halved',
            default         => 'fa-solid fa-sliders',
        };
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'company'       => 'Company Details',
            'leave'         => 'Leave Policy',
            'timesheet'     => 'Timesheet Rules',
            'payroll'       => 'Payroll & Billing',
            'notifications' => 'Notifications',
            'security'      => 'Security',
            default         => ucfirst($category),
        };
    }

    public function render()
    {
        return view('livewire.admin.system-config')
            ->layout('layouts.portal', ['title' => 'System Configuration', 'breadcrumb' => 'System Configuration']);
    }
}