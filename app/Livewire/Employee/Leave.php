<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

class Leave extends Component
{
    use WithFileUploads;

    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $tab = 'apply';

    public array $balances = [];
    public array $policies = [];
    public array $myRequests = [];
    public array $myHalfDays = [];

    public string $leaveType = 'annual';
    public string $startDate = '';
    public string $endDate = '';

    public array $reasonOptions = [];
    public string $reasonOption = '';
    public string $reasonCustom = '';
    public string $handover = '';
    public $attachment = null;
    public bool $refining = false;

    public array $conflicts = [];

    public bool $showHalfDay = false;
    public array $halfDayForm = [];

    public function mount(): void
    {
        $this->startDate = now()->addDay()->format('Y-m-d');
        $this->endDate = now()->addDay()->format('Y-m-d');
        $this->halfDayForm = [
            'notice_date' => now()->addDay()->format('Y-m-d'),
            'leave_time' => '14:00',
            'reason' => '',
        ];

        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $balance = Api::get('/leave/balance');
        if ($balance['ok']) {
            $this->balances = $balance['data']['balances'] ?? [];
            $this->policies = $balance['data']['policies'] ?? [];
        }

        $this->loadReasons();
        $this->loadMyRequests();
        $this->loadMyHalfDays();

        $this->loading = false;
    }

    protected function loadReasons(): void
    {
        $response = Api::get('/leave/reasons/' . $this->leaveType);

        $options = $response['ok'] ? ($response['data'] ?? []) : [];

        if (count($options) === 0) {
            // Fallback if SQL seed was not applied: single "Other" so the form still works.
            $options = [['id' => 0, 'label' => 'Other', 'is_other' => true]];
        }

        $this->reasonOptions = $options;

        if (empty($this->reasonOption) || ! collect($options)->contains('label', $this->reasonOption)) {
            $this->reasonOption = $options[0]['label'] ?? '';
        }
    }

    protected function loadMyRequests(): void
    {
        $response = Api::get('/leave/history', ['per_page' => 30]);

        $this->myRequests = $response['ok']
            ? ($response['data']['data'] ?? [])
            : [];
    }

    protected function loadMyHalfDays(): void
    {
        $response = Api::get('/half-day/mine');

        $this->myHalfDays = $response['ok'] ? ($response['data'] ?? []) : [];
    }

    public function updatedLeaveType(): void
    {
        $this->loadReasons();
        $this->reasonCustom = '';
    }

    public function updatedStartDate(): void
    {
        if (Carbon::parse($this->endDate)->lt(Carbon::parse($this->startDate))) {
            $this->endDate = $this->startDate;
        }
        $this->checkConflicts();
    }

    public function updatedEndDate(): void
    {
        $this->checkConflicts();
    }

    protected function checkConflicts(): void
    {
        if (! $this->startDate || ! $this->endDate) {
            $this->conflicts = [];
            return;
        }

        $range = $this->startDate . '_' . $this->endDate;
        $response = Api::get('/leave/conflicts/' . $range);

        if ($response['ok']) {
            $this->conflicts = $response['data']['conflicts'] ?? [];
        }
    }

    public function getIsOtherReasonProperty(): bool
    {
        $selected = collect($this->reasonOptions)->firstWhere('label', $this->reasonOption);

        return (bool) ($selected['is_other'] ?? false);
    }

    public function getDaysTakenProperty(): float
    {
        if (! $this->startDate || ! $this->endDate) return 0;

        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);

        if ($end->lt($start)) return 0;

        $count = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if (! $current->isWeekend()) $count++;
            $current->addDay();
        }

        return (float) $count;
    }

    public function getLeaveTypesProperty(): array
    {
        return collect($this->balances)
            ->keys()
            ->mapWithKeys(fn ($key) => [$key => ucfirst($key) . ' Leave'])
            ->toArray();
    }

    public function switchTab(string $tab): void
    {
        if (in_array($tab, ['apply', 'requests', 'half-day'], true)) {
            $this->tab = $tab;

            if ($tab === 'requests') $this->loadMyRequests();
            if ($tab === 'half-day') $this->loadMyHalfDays();
        }
    }

    public function refineReason(): void
    {
        if (strlen(trim($this->reasonCustom)) < 3) {
            $this->error = 'Type a rough reason first, then click Refine.';
            return;
        }

        $this->refining = true;
        $response = Api::post('/ai/refine-leave-reason', [
            'rough_reason' => $this->reasonCustom,
            'leave_type' => $this->leaveType,
        ]);

        if ($response['ok']) {
            $this->reasonCustom = $response['data']['text'] ?? $this->reasonCustom;
            $this->flash = 'Reason refined.';
        } else {
            $this->error = $response['message'] ?? 'AI is unavailable right now.';
        }

        $this->refining = false;
    }

    public function resetForm(): void
    {
        $this->leaveType = 'annual';
        $this->startDate = now()->addDay()->format('Y-m-d');
        $this->endDate = now()->addDay()->format('Y-m-d');
        $this->reasonCustom = '';
        $this->handover = '';
        $this->attachment = null;
        $this->flash = null;
        $this->error = null;
        $this->conflicts = [];
        $this->loadReasons();
    }

    public function submit(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->reasonOption)) {
            $this->error = 'Select a reason for leave.';
            return;
        }

        if ($this->isOtherReason && strlen(trim($this->reasonCustom)) < 3) {
            $this->error = 'Describe the reason (at least 3 characters).';
            return;
        }

        if ($this->daysTaken <= 0) {
            $this->error = 'No working days in the selected range.';
            return;
        }

        if (! empty($this->conflicts)) {
            $this->error = 'There is a conflict with a teammate on leave.';
            return;
        }

        $reason = $this->isOtherReason ? $this->reasonCustom : $this->reasonOption;

        if (! empty($this->handover)) {
            $reason .= "\n\nHandover: " . $this->handover;
        }

        $payload = [
            'leave_type' => $this->leaveType,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'reason' => $reason,
        ];

        $response = $this->attachment
            ? Api::upload('/leave/apply', $payload, ['attachment' => $this->attachment])
            : Api::post('/leave/apply', $payload);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first() ?: ($response['message'] ?? 'Failed.');
            return;
        }

        $this->flash = 'Leave request submitted.';
        $this->resetForm();
        $this->load();
    }

    public function openHalfDay(): void
    {
        $this->halfDayForm = [
            'notice_date' => now()->addDay()->format('Y-m-d'),
            'leave_time' => '14:00',
            'reason' => '',
        ];
        $this->showHalfDay = true;
    }

    public function closeHalfDay(): void
    {
        $this->showHalfDay = false;
    }

    public function submitHalfDay(): void
    {
        $this->error = null;

        $response = Api::post('/half-day/request', $this->halfDayForm);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first() ?: ($response['message'] ?? 'Failed.');
            return;
        }

        $this->flash = 'Half-day notice sent to your manager.';
        $this->closeHalfDay();
        $this->loadMyHalfDays();
    }

    public function render()
    {
        return view('livewire.employee.leave')
            ->layout('layouts.portal', ['title' => 'Leave', 'breadcrumb' => 'Leave']);
    }
}