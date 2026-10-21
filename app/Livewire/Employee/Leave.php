<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

class Leave extends Component
{
    use WithFileUploads;

    public bool    $loading = true;
    public ?string $error   = null;
    public ?string $flash   = null;

    public array $balances = [];

    // form state
    public string $leaveType  = 'annual';
    public string $startDate  = '';
    public string $endDate    = '';
    public bool   $isHalfDay  = false;
    public string $reason     = '';
    public string $handover   = '';
    public $attachment        = null;

    public array  $conflicts = [];

    public array $policies = []; 

    public function mount(): void
    {
        $this->startDate = now()->addDay()->format('Y-m-d');
        $this->endDate   = now()->addDay()->format('Y-m-d');
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error   = null;

        $response = Api::get('/leave/balance');

        if ($response['ok']) {
            $this->balances = $response['data']['balances'] ?? [];
            $this->policies = $response['data']['policies'] ?? [];
        } else {
            $this->error = $response['message'] ?? 'Unable to load leave balances.';
        }

        $this->loading = false;
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

    public function updatedIsHalfDay(): void
    {
        if ($this->isHalfDay) {
            $this->endDate = $this->startDate;
        }
    }

    protected function checkConflicts(): void
    {
        if (!$this->startDate || !$this->endDate) {
            $this->conflicts = [];
            return;
        }

        $range = $this->startDate . '_' . $this->endDate;
        $response = Api::get('/leave/conflicts/' . $range);

        if ($response['ok']) {
            $this->conflicts = $response['data']['conflicts'] ?? [];
        }
    }

    // ============ computed getters ============

    public function getDaysTakenProperty(): float
    {
        if ($this->isHalfDay) {
            return 0.5;
        }

        if (!$this->startDate || !$this->endDate) {
            return 0;
        }

        $start = Carbon::parse($this->startDate);
        $end   = Carbon::parse($this->endDate);

        if ($end->lt($start)) {
            return 0;
        }

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

    public function getLeaveTypesProperty(): array
    {
        // driven by what the API returned in balances
        return collect($this->balances)
            ->keys()
            ->mapWithKeys(fn ($key) => [
                $key => ucfirst($key) . ' Leave',
            ])
            ->toArray();
    }

    // ============ actions ============

    public function resetForm(): void
    {
        $this->leaveType = 'annual';
        $this->startDate = now()->addDay()->format('Y-m-d');
        $this->endDate   = now()->addDay()->format('Y-m-d');
        $this->isHalfDay = false;
        $this->reason    = '';
        $this->handover  = '';
        $this->attachment = null;
        $this->flash     = null;
        $this->error     = null;
        $this->conflicts = [];
    }

    public function submit(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->startDate) || empty($this->endDate)) {
            $this->error = 'Please select a date range.';
            return;
        }

        if (empty($this->reason)) {
            $this->error = 'Please provide a reason for leave.';
            return;
        }

        $days = $this->daysTaken;

        if ($days <= 0) {
            $this->error = 'No working days in the selected range.';
            return;
        }

        // client-side conflict guard — user must acknowledge
        if (!empty($this->conflicts)) {
            $this->error = 'There is a conflict with a teammate on leave. Please adjust dates or ask your manager for an override.';
            return;
        }

        $payload = [
            'leave_type' => $this->leaveType,
            'start_date' => $this->startDate,
            'end_date'   => $this->endDate,
            'reason'     => trim($this->reason . "\n\nHandover: " . $this->handover),
        ];

        if ($this->attachment) {
            $response = Api::upload('/leave/apply', $payload, [
                'attachment' => $this->attachment,
            ]);
        } else {
            $response = Api::post('/leave/apply', $payload);
        }

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $firstError = collect($errs)->flatten()->first();
            $this->error = $firstError ?: ($response['message'] ?? 'Failed to submit leave request.');
            return;
        }

        $this->flash = 'Leave request submitted. You will be notified once it has been reviewed.';
        $this->resetForm();
        $this->load();
    }

    public function render()
    {
        return view('livewire.employee.leave')
            ->layout('layouts.portal', ['title' => 'Leave Request', 'breadcrumb' => 'Leave Request']);
    }
}