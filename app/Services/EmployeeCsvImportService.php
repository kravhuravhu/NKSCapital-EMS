<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\LeaveConfig;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeCsvImportService
{
    public const REQUIRED_COLUMNS = [
        'first_name', 'last_name', 'email', 'employee_type', 'service_type', 'role',
    ];

    public const VALID_EMPLOYEE_TYPES = ['ps', 'pr'];
    public const VALID_SERVICE_TYPES = ['permanent', 'contractor', 'temporary', 'intern'];
    public const VALID_ROLES = ['employee', 'manager', 'director', 'admin', 'recruiter'];

    /**
     * Parse a CSV file and return a preview. Does not write anything.
     */
    public function preview(string $path): array
    {
        return $this->walk($path, dryRun: true);
    }

    /**
     * Import the CSV, creating users where the preview passed.
     */
    public function import(string $path, User $actor): array
    {
        return $this->walk($path, dryRun: false, actor: $actor);
    }

    protected function walk(string $path, bool $dryRun, ?User $actor = null): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        $header = array_map('trim', fgetcsv($handle));

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);

        if (! empty($missing)) {
            fclose($handle);
            throw new \RuntimeException('Missing required columns: ' . implode(', ', $missing));
        }

        $clients = Client::pluck('id', 'company_name')->toArray();
        $projects = Project::pluck('id', 'project_code')->toArray();
        $managers = User::whereNotNull('email')->pluck('id', 'email')->toArray();

        $annualDefault = (float) (LeaveConfig::where('leave_type', 'annual')->value('default_entitlement') ?? 0);
        $sickDefault = (float) (LeaveConfig::where('leave_type', 'sick')->value('default_entitlement') ?? 0);
        $familyDefault = (float) (LeaveConfig::where('leave_type', 'family')->value('default_entitlement') ?? 0);

        $onboarding = app(OnboardingService::class);

        $rows = [];
        $created = 0;
        $skipped = 0;
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($row) !== count($header)) {
                $rows[] = [
                    'row_number' => $rowNumber,
                    'email' => $row[2] ?? '',
                    'status' => 'error',
                    'message' => 'Column count mismatch',
                ];
                $skipped++;
                continue;
            }

            $data = array_combine($header, array_map('trim', $row));

            $errors = $this->validateRow($data, $clients, $projects, $managers);

            if (! empty($errors)) {
                $rows[] = [
                    'row_number' => $rowNumber,
                    'email' => $data['email'] ?? '',
                    'status' => 'error',
                    'message' => implode('; ', $errors),
                ];
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $rows[] = [
                    'row_number' => $rowNumber,
                    'email' => $data['email'],
                    'status' => 'ready',
                    'message' => 'Will be created',
                ];
                continue;
            }

            try {
                DB::transaction(function () use (
                    $data, $clients, $projects, $managers, $actor, $onboarding,
                    $annualDefault, $sickDefault, $familyDefault
                ) {
                    $employeeNumber = EmployeeNumberService::generate();
                    $tempPassword = Str::random(12);

                    $user = User::create([
                        'employee_number' => $employeeNumber,
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'email' => $data['email'],
                        'id_number' => $data['id_number'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'password' => Hash::make($tempPassword),
                        'department' => $data['department'] ?? null,
                        'position' => $data['position'] ?? null,
                        'role' => $data['role'],
                        'employee_type' => $data['employee_type'],
                        'service_type' => $data['service_type'],
                        'manager_id' => ! empty($data['manager_email']) ? ($managers[$data['manager_email']] ?? null) : null,
                        'client_id' => ! empty($data['client_name']) ? ($clients[$data['client_name']] ?? null) : null,
                        'project_id' => ! empty($data['project_code']) ? ($projects[$data['project_code']] ?? null) : null,
                        'hire_date' => $data['hire_date'] ?? now()->toDateString(),
                        'is_active' => true,
                        'pending_company_email' => true,
                        'leave_balance_annual' => $annualDefault,
                        'leave_balance_sick' => $sickDefault,
                        'leave_balance_family' => $familyDefault,
                    ]);

                    $user->assignRole($data['role']);

                    Contract::create([
                        'user_id' => $user->id,
                        'version' => 1,
                        'file_path' => '',
                        'effective_date' => $data['hire_date'] ?? now()->toDateString(),
                        'salary_annual' => null,
                        'notice_period_days' => 30,
                        'position' => $data['position'] ?? null,
                        'contract_type' => 'permanent',
                        'status' => 'pending_signature',
                        'uploaded_by' => $actor->id,
                    ]);

                    $onboarding->seed($user);

                    AuditService::log(
                        action: 'EMPLOYEE_CSV_IMPORTED',
                        tableName: 'users',
                        recordId: $user->id,
                        newValues: [
                            'email' => $user->email,
                            'employee_number' => $employeeNumber,
                            'batch_actor_id' => $actor->id,
                        ],
                        logType: 'success'
                    );
                });

                $rows[] = [
                    'row_number' => $rowNumber,
                    'email' => $data['email'],
                    'status' => 'created',
                    'message' => 'Created',
                ];
                $created++;
            } catch (\Throwable $e) {
                $rows[] = [
                    'row_number' => $rowNumber,
                    'email' => $data['email'],
                    'status' => 'error',
                    'message' => 'Import failed: ' . $e->getMessage(),
                ];
                $skipped++;
            }
        }

        fclose($handle);

        return [
            'created' => $created,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    protected function validateRow(array $data, array $clients, array $projects, array $managers): array
    {
        $errors = [];

        if (empty($data['first_name'])) $errors[] = 'first_name required';
        if (empty($data['last_name'])) $errors[] = 'last_name required';

        if (empty($data['email'])) {
            $errors[] = 'email required';
        } elseif (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'invalid email';
        } elseif (User::where('email', $data['email'])->exists()) {
            $errors[] = 'email already exists';
        }

        if (! in_array($data['employee_type'] ?? '', self::VALID_EMPLOYEE_TYPES, true)) {
            $errors[] = 'employee_type must be ps or pr';
        }

        if (! in_array($data['service_type'] ?? '', self::VALID_SERVICE_TYPES, true)) {
            $errors[] = 'service_type must be permanent, contractor, temporary, or intern';
        }

        if (! in_array($data['role'] ?? '', self::VALID_ROLES, true)) {
            $errors[] = 'role must be employee, manager, director, admin, or recruiter';
        }

        if (($data['employee_type'] ?? '') === 'ps') {
            if (empty($data['client_name'])) {
                $errors[] = 'PS employee requires client_name';
            } elseif (! isset($clients[$data['client_name']])) {
                $errors[] = "client '{$data['client_name']}' not found";
            }
        }

        if (($data['employee_type'] ?? '') === 'pr') {
            if (empty($data['project_code'])) {
                $errors[] = 'PR employee requires project_code';
            } elseif (! isset($projects[$data['project_code']])) {
                $errors[] = "project '{$data['project_code']}' not found";
            }
        }

        if (! empty($data['manager_email']) && ! isset($managers[$data['manager_email']])) {
            $errors[] = "manager '{$data['manager_email']}' not found";
        }

        return $errors;
    }

    public static function templateCsv(): string
    {
        $header = [
            'first_name', 'last_name', 'email', 'employee_type', 'service_type',
            'role', 'department', 'position', 'hire_date', 'manager_email',
            'client_name', 'project_code', 'id_number', 'phone',
        ];

        return implode(',', $header) . "\n"
            . "John,Doe,john.doe@example.com,pr,permanent,employee,Engineering,Developer,2026-01-15,manager@nkscapital.co.za,,NKS-2026-001,8501015009087,0821234567\n"
            . "Jane,Smith,jane.smith@example.com,ps,contractor,employee,Consulting,Senior Consultant,2026-02-01,,Acme Holdings,,9002024800081,0839876543\n";
    }
}