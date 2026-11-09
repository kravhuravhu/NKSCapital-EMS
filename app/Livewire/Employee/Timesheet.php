<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

class Timesheet extends Component
{
    use WithFileUploads;

    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $monthYear = '';
    public string $notes = '';
    public string $workflow = 'pr';

    public ?array $timesheet = null;
    public array $entries = [];
    public array $projects = [];
    public array $leaveBalances = [];
    public array $leaveDays = [];

    public array $historyItems = [];
    public bool $showHistory = false;
    public bool $showPreview = false;

    public bool $showAiFill = false;
    public string $aiPrompt = '';
    public bool $aiGenerating = false;
    public array $aiPreview = [];

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
        $this->error = null;

        $user = auth()->user();
        $this->workflow = $user->employee_type === 'ps' ? 'ps' : 'pr';

        $this->loadProjects();
        $this->loadLeaveBalances();
        $this->loadLeaveDays();
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

    protected function loadLeaveDays(): void
    {
        $start = Carbon::parse($this->monthYear)->startOfMonth()->format('Y-m-d');
        $end = Carbon::parse($this->monthYear)->endOfMonth()->format('Y-m-d');

        $response = Api::get('/leave/my-days', ['from' => $start, 'to' => $end]);

        if (! $response['ok']) {
            $this->leaveDays = [];
            return;
        }

        $map = [];
        foreach ($response['data']['days'] ?? [] as $day) {
            $map[$day['date']] = [
                'leave_type' => $day['leave_type'] ?? 'leave',
                'is_pending_proof' => (bool) ($day['is_pending_proof'] ?? false),
            ];
        }

        $this->leaveDays = $map;
    }

    protected function loadTimesheet(): void
    {
        $month = Carbon::parse($this->monthYear)->startOfMonth()->format('Y-m-d');

        $path = $this->workflow === 'ps'
            ? '/timesheet/ps/history'
            : '/timesheet/pr/history';

        $response = Api::get($path);

        $loaded = [];
        $this->timesheet = null;

        if ($response['ok']) {
            $items = $response['data']['data'] ?? $response['data'] ?? [];

            $current = collect($items)->first(function ($row) use ($month) {
                $rowMonth = isset($row['month_year'])
                    ? Carbon::parse($row['month_year'])->startOfMonth()->format('Y-m-d')
                    : null;

                return $rowMonth === $month;
            });

            if ($current) {
                $this->timesheet = $current;
                $this->notes = $current['notes'] ?? '';

                $detailPath = $this->workflow === 'ps'
                    ? '/timesheet/ps/' . $current['id']
                    : '/timesheet/pr/' . $current['id'];

                $details = Api::get($detailPath);

                if ($details['ok']) {
                    $loaded = collect($details['data']['timesheet']['details'] ?? [])
                        // Drop any stale entry that sits on a leave day, from before this fix
                        ->reject(fn ($d) => isset($this->leaveDays[$d['work_date']]))
                        ->map(fn ($d) => [
                            'id' => $d['id'] ?? null,
                            'work_date' => $d['work_date'],
                            'project_id' => $d['project_id'],
                            'project_name' => $d['project']['name'] ?? ($d['project']['project_code'] ?? ''),
                            'task_description' => $d['task_description'] ?? '',
                            'hours_worked' => number_format((float) $d['hours_worked'], 2, '.', ''),
                            'overtime_hours' => number_format((float) ($d['is_overtime'] ? $d['hours_worked'] : 0), 2, '.', ''),
                            'is_overtime' => (bool) ($d['is_overtime'] ?? false),
                        ])
                        ->values()
                        ->toArray();
                }
            }
        }

        usort($loaded, fn ($a, $b) => strcmp($a['work_date'], $b['work_date']));

        $this->entries = $loaded;
    }

    public function updatedMonthYear(): void
    {
        $this->loadLeaveDays();
        $this->loadTimesheet();
    }

    public function isLeaveDay(string $date): bool
    {
        return isset($this->leaveDays[$date]);
    }

    public function leaveInfoFor(string $date): ?array
    {
        return $this->leaveDays[$date] ?? null;
    }

    public function getLeaveDaysInMonthProperty(): array
    {
        $monthStart = Carbon::parse($this->monthYear)->startOfMonth();
        $monthEnd = Carbon::parse($this->monthYear)->endOfMonth();

        $filtered = [];
        foreach ($this->leaveDays as $date => $info) {
            $dateObj = Carbon::parse($date);
            if ($dateObj->lt($monthStart) || $dateObj->gt($monthEnd)) continue;
            if ($dateObj->isWeekend()) continue;
            $filtered[$date] = $info;
        }

        ksort($filtered);

        return $filtered;
    }

    /**
     * Returns the leave day groups as formatted ranges for the banner.
     */
    public function getLeaveDayGroupsProperty(): array
    {
        $days = array_keys($this->getLeaveDaysInMonthProperty());

        if (count($days) === 0) return [];

        $groups = [];
        $current = null;

        foreach ($days as $date) {
            $info = $this->leaveDays[$date];
            $type = $info['leave_type'];
            $dateObj = Carbon::parse($date);

            if ($current === null) {
                $current = ['type' => $type, 'start' => $dateObj, 'end' => $dateObj];
                continue;
            }

            if ($current['type'] === $type && $current['end']->copy()->addDay()->format('Y-m-d') === $date) {
                $current['end'] = $dateObj;
                continue;
            }

            $groups[] = $current;
            $current = ['type' => $type, 'start' => $dateObj, 'end' => $dateObj];
        }

        if ($current !== null) $groups[] = $current;

        return array_map(fn ($g) => [
            'type' => $g['type'],
            'start' => $g['start']->format('d M'),
            'end' => $g['end']->format('d M'),
            'same_day' => $g['start']->equalTo($g['end']),
        ], $groups);
    }

    public function addRow(): void
    {
        $next = $this->nextWorkingDate();

        if ($next === null) {
            $this->error = 'No available working days left in this month (weekends and leave days are excluded).';
            return;
        }

        $this->entries[] = [
            'id' => null,
            'work_date' => $next,
            'project_id' => $this->projects[0]['project']['id'] ?? '',
            'project_name' => '',
            'task_description' => '',
            'hours_worked' => '8.00',
            'overtime_hours' => '0.00',
            'is_overtime' => false,
        ];

        $this->resequenceDates();
    }

    public function duplicateRow(int $index): void
    {
        if (! isset($this->entries[$index])) return;

        $copy = $this->entries[$index];
        $copy['id'] = null;
        $this->entries[] = $copy;

        $this->resequenceDates();
    }

    public function deleteRow(int $index): void
    {
        if (! isset($this->entries[$index])) return;

        $row = $this->entries[$index];

        $hasContent = ! empty($row['project_name'])
            || ! empty($row['task_description'])
            || ((float) $row['hours_worked'] > 0)
            || ((float) $row['overtime_hours'] > 0);

        if ($hasContent) {
            $this->entries[$index]['project_name'] = '';
            $this->entries[$index]['task_description'] = '';
            $this->entries[$index]['hours_worked'] = '0.00';
            $this->entries[$index]['overtime_hours'] = '0.00';
            return;
        }

        unset($this->entries[$index]);
        $this->entries = array_values($this->entries);
        $this->resequenceDates();
    }

    /**
     * Renumber every entry's work_date from the first valid working day,
     * skipping weekends and any day the employee is on leave.
     */
    protected function resequenceDates(): void
    {
        $monthStart = Carbon::parse($this->monthYear)->startOfMonth();
        $monthEnd = Carbon::parse($this->monthYear)->endOfMonth();
        $cursor = $monthStart->copy();

        $this->advancePastBlockedDays($cursor, $monthEnd);

        foreach ($this->entries as $i => $entry) {
            $this->advancePastBlockedDays($cursor, $monthEnd);

            if ($cursor->gt($monthEnd)) {
                $this->entries[$i]['work_date'] = $monthEnd->copy()->format('Y-m-d');
                continue;
            }

            $this->entries[$i]['work_date'] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }
    }

    protected function advancePastBlockedDays(Carbon $cursor, Carbon $monthEnd): void
    {
        while (! $cursor->gt($monthEnd)
            && ($cursor->isWeekend() || $this->isLeaveDay($cursor->format('Y-m-d')))) {
            $cursor->addDay();
        }
    }

    /**
     * Next available working date that is not a weekend and not a leave day.
     * Returns null when the month is exhausted.
     */
    protected function nextWorkingDate(): ?string
    {
        $monthStart = Carbon::parse($this->monthYear)->startOfMonth();
        $monthEnd = Carbon::parse($this->monthYear)->endOfMonth();

        $latest = collect($this->entries)
            ->pluck('work_date')
            ->filter()
            ->map(fn ($d) => Carbon::parse($d))
            ->sortDesc()
            ->first();

        $cursor = ($latest ?? $monthStart->copy()->subDay())->copy()->addDay();

        $this->advancePastBlockedDays($cursor, $monthEnd);

        if ($cursor->gt($monthEnd)) {
            // Wrap to the beginning and look for the first available day
            $cursor = $monthStart->copy();
            $this->advancePastBlockedDays($cursor, $monthEnd);

            if ($cursor->gt($monthEnd)) return null;

            $used = collect($this->entries)->pluck('work_date')->all();

            while (in_array($cursor->format('Y-m-d'), $used, true)) {
                $cursor->addDay();
                $this->advancePastBlockedDays($cursor, $monthEnd);

                if ($cursor->gt($monthEnd)) return null;
            }
        }

        return $cursor->format('Y-m-d');
    }

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
        return count($this->getLeaveDaysInMonthProperty());
    }

    public function getProgressPercentProperty(): int
    {
        $target = $this->workingDaysTarget * 8;
        if ($target <= 0) return 0;

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
        $end = Carbon::parse($this->monthYear)->endOfMonth();
        $count = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if (! $current->isWeekend() && ! $this->isLeaveDay($current->format('Y-m-d'))) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    /**
     * Client-side guard so we never send a payload the backend will reject.
     */
    protected function findLeaveConflicts(): array
    {
        $conflicts = [];

        foreach ($this->entries as $entry) {
            $date = $entry['work_date'];
            $hasHours = (float) $entry['hours_worked'] > 0 || (float) $entry['overtime_hours'] > 0;

            if ($hasHours && $this->isLeaveDay($date)) {
                $conflicts[] = Carbon::parse($date)->format('d M Y');
            }
        }

        return $conflicts;
    }

    public function createTimesheet(): void
    {
        $this->error = null;

        $conflicts = $this->findLeaveConflicts();
        if (! empty($conflicts)) {
            $this->error = 'These rows fall on leave days: ' . implode(', ', $conflicts) . '. Remove their hours first.';
            return;
        }

        $response = Api::post(
            $this->workflow === 'ps' ? '/timesheet/ps/create' : '/timesheet/pr/create',
            [
                'month_year' => $this->monthYear,
                'entries' => $this->entries,
                'notes' => $this->notes,
            ]
        );

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to create timesheet.');
            return;
        }

        $this->flash = 'Timesheet created.';
        $this->loadTimesheet();
    }

    public function saveDraft(): void
    {
        if (! $this->timesheet) {
            $this->createTimesheet();
            return;
        }

        $this->error = null;

        $conflicts = $this->findLeaveConflicts();
        if (! empty($conflicts)) {
            $this->error = 'These rows fall on leave days: ' . implode(', ', $conflicts) . '. Remove their hours first.';
            return;
        }

        $response = Api::put('/timesheet/pr/save-draft', [
            'timesheet_id' => $this->timesheet['id'],
            'entries' => $this->entries,
        ]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to save draft.');
            return;
        }

        $this->flash = 'Draft saved.';
    }

    public function submitForApproval(): void
    {
        if (! $this->timesheet) {
            $this->createTimesheet();
            return;
        }

        $this->error = null;

        $conflicts = $this->findLeaveConflicts();
        if (! empty($conflicts)) {
            $this->error = 'These rows fall on leave days: ' . implode(', ', $conflicts) . '. Remove their hours first.';
            return;
        }

        $saveResponse = Api::put('/timesheet/pr/save-draft', [
            'timesheet_id' => $this->timesheet['id'],
            'entries' => $this->entries,
        ]);

        if (! $saveResponse['ok']) {
            $errs = $saveResponse['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($saveResponse['message'] ?? 'Unable to sync draft before submitting.');
            return;
        }

        $response = Api::post('/timesheet/pr/submit', [
            'timesheet_id' => $this->timesheet['id'],
        ]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to submit.');
            return;
        }

        $this->flash = 'Submitted for approval.';
        $this->loadTimesheet();
    }

    public function generateTemplate(): void
    {
        if (! $this->timesheet) {
            $this->error = 'Create the timesheet first.';
            return;
        }

        $response = Api::post('/timesheet/ps/generate-template', ['timesheet_id' => $this->timesheet['id']]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed.';
            return;
        }

        $this->flash = 'Client template generated.';
        $this->loadTimesheet();
    }

    public function uploadSigned(): void
    {
        if (! $this->signedFile || ! $this->timesheet) {
            $this->error = 'Select a signed PDF first.';
            return;
        }

        $response = Api::upload(
            '/timesheet/ps/upload-signed',
            ['timesheet_id' => $this->timesheet['id']],
            ['signed_file' => $this->signedFile]
        );

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed.';
            return;
        }

        $this->flash = 'Signed file uploaded.';
        $this->signedFile = null;
        $this->loadTimesheet();
    }

    public function sendToClient(): void
    {
        if (! $this->timesheet || ! $this->clientEmail) {
            $this->error = 'Enter the client manager email first.';
            return;
        }

        $response = Api::post('/timesheet/ps/send-to-client', [
            'timesheet_id' => $this->timesheet['id'],
            'client_manager_email' => $this->clientEmail,
        ]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed.';
            return;
        }

        $this->flash = 'Sent to client.';
        $this->loadTimesheet();
    }

    public function openHistory(): void
    {
        $this->historyItems = [];
        $this->showHistory = true;

        $response = Api::get($this->workflow === 'ps' ? '/timesheet/ps/history' : '/timesheet/pr/history');

        if ($response['ok']) {
            $this->historyItems = $response['data']['data'] ?? $response['data'] ?? [];
        }
    }

    public function closeHistory(): void
    {
        $this->showHistory = false;
        $this->historyItems = [];
    }

    public function loadHistoryMonth(string $monthYear): void
    {
        $this->monthYear = $monthYear;
        $this->closeHistory();
        $this->updatedMonthYear();
    }

    public function openPreview(): void
    {
        if (count($this->entries) === 0) {
            $this->error = 'Add at least one entry before previewing.';
            return;
        }

        $this->showPreview = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
    }

    public function openAiFill(): void
    {
        $this->aiPrompt = '';
        $this->aiPreview = [];
        $this->showAiFill = true;
        $this->error = null;
    }

    public function closeAiFill(): void
    {
        $this->showAiFill = false;
        $this->aiPrompt = '';
        $this->aiPreview = [];
    }

    public function generateWithAi(): void
    {
        if (strlen(trim($this->aiPrompt)) < 20) {
            $this->error = 'Write a bit more about your month (at least 20 characters).';
            return;
        }

        $this->aiGenerating = true;
        $this->error = null;
        $this->aiPreview = [];

        // Work days: weekdays minus leave days, so the AI never produces leave-day entries
        $monthStart = Carbon::parse($this->monthYear)->startOfMonth();
        $monthEnd = Carbon::parse($this->monthYear)->endOfMonth();

        $workDays = [];
        $cursor = $monthStart->copy();

        while ($cursor->lte($monthEnd)) {
            $dateStr = $cursor->format('Y-m-d');

            if (! $cursor->isWeekend() && ! $this->isLeaveDay($dateStr) && $cursor->lte(now())) {
                $workDays[] = $dateStr;
            }

            $cursor->addDay();
        }

        if (count($workDays) === 0) {
            $this->error = 'No working days available in this month yet.';
            $this->aiGenerating = false;
            return;
        }

        $projectList = collect($this->projects)->map(fn ($p) => [
            'id' => $p['project']['id'] ?? null,
            'name' => $p['project']['name'] ?? ($p['project']['project_code'] ?? ''),
        ])->filter(fn ($p) => $p['id'] !== null)->values()->toArray();

        $response = Api::post('/ai/autofill-timesheet', [
            'month' => $this->monthYear,
            'work_days' => $workDays,
            'projects' => $projectList,
            'description' => $this->aiPrompt,
        ]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'AI is unavailable right now.';
            $this->aiGenerating = false;
            return;
        }

        $entries = $response['data']['entries'] ?? [];

        if (count($entries) === 0) {
            $this->error = 'The AI returned no usable entries. Try a longer description.';
            $this->aiGenerating = false;
            return;
        }

        // Sanity filter, never trust the model blindly
        $projectIds = array_column($projectList, 'id');

        $this->aiPreview = collect($entries)
            ->filter(function ($e) use ($projectIds) {
                $date = $e['work_date'] ?? null;
                $pid = $e['project_id'] ?? null;

                return $date
                    && in_array($date, $workDays, true)
                    && in_array($pid, $projectIds, true);
            })
            ->map(fn ($e) => [
                'work_date' => $e['work_date'],
                'project_id' => $e['project_id'],
                'project_name' => '',
                'task_description' => mb_substr((string) ($e['task_description'] ?? ''), 0, 200),
                'hours_worked' => number_format((float) ($e['hours'] ?? 8), 2, '.', ''),
                'overtime_hours' => '0.00',
                'is_overtime' => false,
                'id' => null,
            ])
            ->values()
            ->toArray();

        $this->aiGenerating = false;

        if (count($this->aiPreview) === 0) {
            $this->error = 'The AI returned entries that did not match this month. Try again with more detail.';
        }
    }

    public function applyAiPreview(): void
    {
        if (count($this->aiPreview) === 0) {
            $this->error = 'Nothing to apply.';
            return;
        }

        $this->entries = $this->aiPreview;
        $this->resequenceDates();

        $this->flash = 'Timesheet filled with AI suggestions. Review before saving.';
        $this->closeAiFill();
    }

    public static function formatHours(float $hours): string
    {
        $sign = $hours < 0 ? '-' : '';
        $hours = abs($hours);
        $whole = (int) floor($hours);
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