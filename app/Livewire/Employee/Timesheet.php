<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

class Timesheet extends Component
{
    use WithFileUploads;

    public bool    $loading = true;
    public ?string $error   = null;
    public ?string $flash   = null;

    public string $monthYear = '';
    public string $notes     = '';
    public string $workflow  = 'pr';

    public ?array $timesheet = null;
    public array  $entries   = [];
    public array  $projects  = [];
    public array  $leaveBalances = [];

    public $signedFile = null;
    public string $clientEmail = '';

    public function mount(): void
    {
        $this->monthYear = $this->monthYear ?: now()->format('Y-m-d');
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error   = null;

        $user = auth()->user();
        $this->workflow = $user->employee_type === 'ps' ? 'ps' : 'pr';

        $this->loadProjects();
        $this->loadLeaveBalances();
        $this->loadTimesheet();

        $this->loading = false;
    }

    protected function loadProjects(): void
    {
        $response = Api::get('/project/list/assigned');

        if ($response['ok']) {
            $this->projects = $response['data']['assigned_projects'] ?? [];
        }
    }

    protected function loadLeaveBalances(): void
    {
        $response = Api::get('/leave/balance');

        if ($response['ok']) {
            $this->leaveBalances = $response['data']['balances'] ?? [];
        }
    }

    protected function loadTimesheet(): void
    {
        $month = Carbon::parse($this->monthYear)->startOfMonth()->format('Y-m-d');

        $path = $this->workflow === 'ps'
            ? '/timesheet/ps/history'
            : '/timesheet/pr/history';

        $response = Api::get($path);

        if (!$response['ok']) {
            $this->timesheet = null;
            return;
        }

        $items = $response['data']['data'] ?? $response['data'] ?? [];

        $current = collect($items)->first(function ($row) use ($month) {
            $rowMonth = isset($row['month_year'])
                ? Carbon::parse($row['month_year'])->startOfMonth()->format('Y-m-d')
                : null;
            return $rowMonth === $month;
        });

        if (!$current) {
            $this->timesheet = null;
            $this->entries   = [];
            return;
        }

        $this->timesheet = $current;
        $this->notes     = $current['notes'] ?? '';

        $detailPath = $this->workflow === 'ps'
            ? '/timesheet/ps/' . $current['id']
            : '/timesheet/pr/' . $current['id'];

        $details = Api::get($detailPath);

        if ($details['ok']) {
            $this->entries = collect($details['data']['timesheet']['details'] ?? [])
                ->map(fn ($d) => [
                    'id'               => $d['id'] ?? null,
                    'work_date'        => $d['work_date'],
                    'project_id'       => $d['project_id'],
                    'project_name'     => $d['project']['name'] ?? ($d['project']['project_code'] ?? ''),
                    'task_description' => $d['task_description'] ?? '',
                    'hours_worked'     => number_format((float) $d['hours_worked'], 2, '.', ''),
                    'overtime_hours'   => number_format((float) ($d['is_overtime'] ? $d['hours_worked'] : 0), 2, '.', ''),
                    'is_overtime'      => (bool) ($d['is_overtime'] ?? false),
                ])
                ->values()
                ->toArray();
        }
    }

    public function updatedMonthYear(): void
    {
        $this->loadTimesheet();
    }

    // ============ row management ============

    public function addRow(): void
    {
        $this->entries[] = [
            'id'               => null,
            'work_date'        => $this->nextWorkingDate(),
            'project_id'       => $this->projects[0]['project']['id'] ?? '',
            'project_name'     => '',
            'task_description' => '',
            'hours_worked'     => '8.00',
            'overtime_hours'   => '0.00',
            'is_overtime'      => false,
        ];

        $this->resequenceDates();
    }

    public function duplicateRow(int $index): void
    {
        if (!isset($this->entries[$index])) {
            return;
        }

        $copy = $this->entries[$index];
        $copy['id'] = null;

        $this->entries[] = $copy;
        $this->resequenceDates();
    }

    /**
     * First click clears content if there is any.
     * Second click (when the row is empty) removes it entirely.
     * Never leaves a gap in the dates — after removal, dates are re-sequenced.
     */
    public function deleteRow(int $index): void
    {
        if (!isset($this->entries[$index])) {
            return;
        }

        $row = $this->entries[$index];

        $hasContent = !empty($row['project_name'])
            || !empty($row['task_description'])
            || ((float) $row['hours_worked'] > 0)
            || ((float) $row['overtime_hours'] > 0);

        if ($hasContent) {
            // Clear the row's content but keep the row
            $this->entries[$index]['project_name']     = '';
            $this->entries[$index]['task_description'] = '';
            $this->entries[$index]['hours_worked']     = '0.00';
            $this->entries[$index]['overtime_hours']   = '0.00';

            return;
        }

        // Row is empty → remove entirely
        unset($this->entries[$index]);
        $this->entries = array_values($this->entries);

        $this->resequenceDates();
    }

    /**
     * Renumber work_date on every row from the first working day of the month.
     * Skips weekends. This keeps the date column gap-free after deletions.
     */
    protected function resequenceDates(): void
    {
        $monthStart = Carbon::parse($this->monthYear)->startOfMonth();
        $monthEnd   = Carbon::parse($this->monthYear)->endOfMonth();

        $cursor = $monthStart->copy();

        // advance to first working day
        while ($cursor->isWeekend()) {
            $cursor->addDay();
        }

        foreach ($this->entries as $i => $entry) {
            if ($cursor->gt($monthEnd)) {
                // month exhausted — leave remaining rows dated to the last working day
                $this->entries[$i]['work_date'] = $monthEnd->copy()->format('Y-m-d');
                continue;
            }

            $this->entries[$i]['work_date'] = $cursor->format('Y-m-d');

            $cursor->addDay();
            while ($cursor->isWeekend()) {
                $cursor->addDay();
            }
        }
    }

    protected function nextWorkingDate(?Carbon $after = null): string
    {
        $monthStart = Carbon::parse($this->monthYear)->startOfMonth();
        $monthEnd   = Carbon::parse($this->monthYear)->endOfMonth();

        if ($after === null) {
            $latest = collect($this->entries)
                ->pluck('work_date')
                ->filter()
                ->map(fn ($d) => Carbon::parse($d))
                ->sortDesc()
                ->first();

            $after = $latest ?? $monthStart->copy()->subDay();
        }

        $candidate = $after->copy()->addDay();

        if ($candidate->gt($monthEnd)) {
            $candidate = $monthStart->copy();
        }

        while ($candidate->isWeekend()) {
            $candidate->addDay();

            if ($candidate->gt($monthEnd)) {
                $candidate = $monthStart->copy();
                while ($candidate->isWeekend()) {
                    $candidate->addDay();
                }
                break;
            }
        }

        return $candidate->format('Y-m-d');
    }

    // ============ computed getters ============

    public function getRegularHoursProperty(): float
    {
        return collect($this->entries)->sum(fn ($e) => (float) $e['hours_worked']);
    }

    public function getOvertimeHoursProperty(): float
    {
        return collect($this->entries)->sum(fn ($e) => (float) $e['overtime_hours']);
    }

    public function getTotalHoursProperty(): float
    {
        return $this->regularHours + $this->overtimeHours;
    }

    public function getLeaveTakenProperty(): float
    {
        $sum = 0.0;

        foreach ($this->leaveBalances as $balance) {
            $sum += (float) ($balance['used_this_year'] ?? 0);
        }

        return $sum;
    }

    public function getProgressPercentProperty(): int
    {
        $target = $this->workingDaysTarget * 8;
        if ($target <= 0) {
            return 0;
        }
        return min(100, (int) round(($this->totalHours / $target) * 100));
    }

    public function getWorkingDaysCompletedProperty(): int
    {
        return collect($this->entries)
            ->filter(fn ($e) => (float) $e['hours_worked'] > 0)
            ->unique('work_date')
            ->count();
    }

    public function getWorkingDaysTargetProperty(): int
    {
        $start = Carbon::parse($this->monthYear)->startOfMonth();
        $end   = Carbon::parse($this->monthYear)->endOfMonth();

        $count   = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            if (!$current->isWeekend()) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    // ============ actions ============

    public function createTimesheet(): void
    {
        $this->error = null;

        $payload = [
            'month_year' => $this->monthYear,
            'entries'    => $this->entries,
            'notes'      => $this->notes,
        ];

        $path = $this->workflow === 'ps'
            ? '/timesheet/ps/create'
            : '/timesheet/pr/create';

        $response = Api::post($path, $payload);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to create timesheet.';
            return;
        }

        $this->flash = 'Timesheet created.';
        $this->loadTimesheet();
    }

    public function saveDraft(): void
    {
        if (!$this->timesheet) {
            $this->createTimesheet();
            return;
        }

        $this->error = null;

        $response = Api::put('/timesheet/pr/save-draft', [
            'timesheet_id' => $this->timesheet['id'],
            'entries'      => $this->entries,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to save draft.';
            return;
        }

        $this->flash = 'Draft saved.';
    }

    public function submitForApproval(): void
    {
        if (!$this->timesheet) {
            $this->createTimesheet();
            return;
        }

        $this->error = null;

        $response = Api::post('/timesheet/pr/submit', [
            'timesheet_id' => $this->timesheet['id'],
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to submit.';
            return;
        }

        $this->flash = 'Submitted for approval.';
        $this->loadTimesheet();
    }

    public function generateTemplate(): void
    {
        if (!$this->timesheet) {
            $this->error = 'Create the timesheet first.';
            return;
        }

        $this->error = null;

        $response = Api::post('/timesheet/ps/generate-template', [
            'timesheet_id' => $this->timesheet['id'],
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to generate template.';
            return;
        }

        $this->flash = 'Client template generated.';
        $this->loadTimesheet();
    }

    public function uploadSigned(): void
    {
        if (!$this->signedFile || !$this->timesheet) {
            $this->error = 'Select a signed PDF first.';
            return;
        }

        $this->error = null;

        $response = Api::upload('/timesheet/ps/upload-signed', [
            'timesheet_id' => $this->timesheet['id'],
        ], [
            'signed_file' => $this->signedFile,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to upload signed file.';
            return;
        }

        $this->flash      = 'Signed file uploaded.';
        $this->signedFile = null;
        $this->loadTimesheet();
    }

    public function sendToClient(): void
    {
        if (!$this->timesheet || !$this->clientEmail) {
            $this->error = 'Enter the client manager email first.';
            return;
        }

        $this->error = null;

        $response = Api::post('/timesheet/ps/send-to-client', [
            'timesheet_id'         => $this->timesheet['id'],
            'client_manager_email' => $this->clientEmail,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to send to client.';
            return;
        }

        $this->flash = 'Sent to client.';
        $this->loadTimesheet();
    }

    // ============ helpers ============

    /**
     * Format a decimal number of hours as "Xh Ym".
     */
    public static function formatHours(float $hours): string
    {
        $sign    = $hours < 0 ? '-' : '';
        $hours   = abs($hours);
        $whole   = (int) floor($hours);
        $minutes = (int) round(($hours - $whole) * 60);

        if ($minutes === 60) {
            $whole++;
            $minutes = 0;
        }

        return sprintf('%s%dh %02dm', $sign, $whole, $minutes);
    }

    public function render()
    {
        return view('livewire.employee.timesheet')
            ->layout('layouts.portal', ['title' => 'Timesheets', 'breadcrumb' => 'Timesheets']);
    }
}