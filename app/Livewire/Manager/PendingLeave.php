<?php

namespace App\Livewire\Manager;

use App\Support\Api;
use Livewire\Component;

class PendingLeave extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public array $requests = [];
    public array $pagination = [];
    public array $configs = [];
    public int $page = 1;

    public bool $showView = false;
    public ?array $viewData = null;

    public bool $showReject = false;
    public ?int $rejectId = null;
    public string $rejectReason = '';

    public bool $showPartial = false;
    public ?int $partialId = null;
    public int $partialMax = 0;
    public string $partialDays = '';
    public string $partialComment = '';

    public function mount(): void
    {
        $this->loadConfigs();
        $this->load();
    }

    // load leave config once so we can gate the partial button
    protected function loadConfigs(): void
    {
        $response = Api::get('/admin/leave-config');

        if (!$response['ok']) {
            return;
        }

        $rows = $response['data'] ?? [];

        $this->configs = collect($rows)
            ->keyBy('leave_type')
            ->toArray();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $response = Api::get('/leave/pending', [
            'page' => $this->page,
            'per_page' => 15,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load pending leave requests.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];

        $this->requests = $paginator['data'] ?? [];
        $this->pagination = [
            'current_page' => $paginator['current_page'] ?? 1,
            'last_page'    => $paginator['last_page'] ?? 1,
            'total'        => $paginator['total'] ?? 0,
        ];

        $this->loading = false;
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    // partial only when threshold is set and the request exceeds it
    public function canPartial(array $row): bool
    {
        $type = $row['leave_type'] ?? null;

        if (!$type || !isset($this->configs[$type])) {
            return false;
        }

        $cfg = $this->configs[$type];
        $threshold = (int) ($cfg['partial_approve_threshold'] ?? 0);

        if ($threshold <= 0) {
            return false;
        }

        return (float) ($row['days_taken'] ?? 0) > $threshold;
    }

    // flag for the row so the manager can see why partial is available
    public function requiresProofAfter(array $row): bool
    {
        $type = $row['leave_type'] ?? null;

        if (!$type || !isset($this->configs[$type])) {
            return false;
        }

        $cfg = $this->configs[$type];
        $after = (int) ($cfg['requires_proof_after'] ?? 0);

        if ($after <= 0) {
            return false;
        }

        return (float) ($row['days_taken'] ?? 0) > $after;
    }

    public function view(int $id): void
    {
        $this->error = null;

        $row = collect($this->requests)->firstWhere('id', $id);

        if (!$row) {
            $this->error = 'Leave request not found.';
            return;
        }

        $this->viewData = $row;
        $this->showView = true;
    }

    public function closeView(): void
    {
        $this->showView = false;
        $this->viewData = null;
    }

    public function approve(int $id): void
    {
        $this->error = null;
        $this->flash = null;

        $response = Api::post('/leave/approve', [
            'leave_request_id' => $id,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to approve leave request.';
            return;
        }

        $this->flash = 'Leave request approved.';
        $this->closeView();
        $this->load();
    }

    public function openReject(int $id): void
    {
        $this->rejectId = $id;
        $this->rejectReason = '';
        $this->showReject = true;
    }

    public function closeReject(): void
    {
        $this->showReject = false;
        $this->rejectId = null;
        $this->rejectReason = '';
    }

    public function submitReject(): void
    {
        $this->error = null;

        if (!$this->rejectReason) {
            $this->error = 'Please provide a rejection reason.';
            return;
        }

        $response = Api::post('/leave/reject', [
            'leave_request_id' => $this->rejectId,
            'reason' => $this->rejectReason,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to reject leave request.';
            return;
        }

        $this->flash = 'Leave request rejected.';
        $this->closeReject();
        $this->closeView();
        $this->load();
    }

    public function openPartial(int $id): void
    {
        $row = collect($this->requests)->firstWhere('id', $id);

        if (!$row) {
            $this->error = 'Leave request not found.';
            return;
        }

        if (!$this->canPartial($row)) {
            $this->error = 'Partial approval is not available for this request.';
            return;
        }

        $this->partialId = $id;
        $this->partialMax = (int) floor((float) ($row['days_taken'] ?? 0));
        $this->partialDays = (string) max(1, $this->partialMax - 1);
        $this->partialComment = '';
        $this->showPartial = true;
    }

    public function closePartial(): void
    {
        $this->showPartial = false;
        $this->partialId = null;
        $this->partialDays = '';
        $this->partialComment = '';
    }

    public function submitPartial(): void
    {
        $this->error = null;

        $days = (float) $this->partialDays;

        if ($days < 0.5) {
            $this->error = 'Approved days must be at least 0.5.';
            return;
        }

        if ($days >= $this->partialMax) {
            $this->error = 'Approved days must be less than the requested total. Use Approve for a full approval.';
            return;
        }

        $response = Api::post('/leave/partial-approve', [
            'leave_request_id' => $this->partialId,
            'approved_days' => $days,
            'comment' => $this->partialComment ?: null,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to partially approve leave request.';
            return;
        }

        $this->flash = 'Leave request partially approved.';
        $this->closePartial();
        $this->closeView();
        $this->load();
    }

    public static function leaveIcon(string $type): string
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

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'pending' => 'gold',
            'hold'    => 'blue',
            default   => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.manager.pending-leave')
            ->layout('layouts.portal', ['title' => 'Pending Leave', 'breadcrumb' => 'Leave Requests']);
    }
}