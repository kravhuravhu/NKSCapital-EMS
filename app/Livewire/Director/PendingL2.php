<?php

namespace App\Livewire\Director;

use App\Support\Api;
use Livewire\Component;

class PendingL2 extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;
    public ?string $pdfUrl = null;

    public array $timesheets = [];

    public bool $showView = false;
    public ?array $viewData = null;

    public bool $showApprove = false;
    public ?int $approveId = null;
    public string $twoFactorCode = '';
    public string $approveComment = '';

    public bool $showReject = false;
    public ?int $rejectId = null;
    public string $rejectReason = '';

    public bool $twoFactorEnabled = false;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        // 2FA is a hard requirement for L2 — check on every load
        $this->twoFactorEnabled = auth()->user()->hasTwoFactorEnabled();

        $response = Api::get('/timesheet/pr/pending/l2');

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load pending timesheets.';
            $this->loading = false;
            return;
        }

        $this->timesheets = $response['data'] ?? [];
        $this->loading = false;
    }

    public function view(int $id): void
    {
        $this->error = null;

        $response = Api::get('/timesheet/pr/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load timesheet.';
            return;
        }

        $this->viewData = $response['data'] ?? null;
        $this->showView = true;
    }

    public function closeView(): void
    {
        $this->showView = false;
        $this->viewData = null;
    }

    public function openApprove(int $id): void
    {
        $this->error = null;

        if (!$this->twoFactorEnabled) {
            $this->error = 'Two-Factor Authentication must be enabled before you can approve timesheets.';
            return;
        }

        $this->approveId = $id;
        $this->twoFactorCode = '';
        $this->approveComment = '';
        $this->showApprove = true;
    }

    public function closeApprove(): void
    {
        $this->showApprove = false;
        $this->approveId = null;
        $this->twoFactorCode = '';
        $this->approveComment = '';
    }

    public function submitApprove(): void
    {
        $this->error = null;
        $this->flash = null;
        $this->pdfUrl = null;

        // re-check: user might have disabled 2FA in another tab
        $this->twoFactorEnabled = auth()->user()->hasTwoFactorEnabled();

        if (!$this->twoFactorEnabled) {
            $this->error = 'Two-Factor Authentication must be enabled before you can approve timesheets.';
            return;
        }

        if (strlen($this->twoFactorCode) !== 6 || !ctype_digit($this->twoFactorCode)) {
            $this->error = 'Enter the 6-digit code from your authenticator app.';
            return;
        }

        $response = Api::post('/timesheet/pr/approve/l2', [
            'timesheet_id' => $this->approveId,
            '2fa_code' => $this->twoFactorCode,
            'comment' => $this->approveComment ?: null,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to approve timesheet.');
            return;
        }

        $this->flash = 'Timesheet approved. PDF generated and ready for payroll.';
        $this->pdfUrl = $response['data']['pdf_url'] ?? null;

        $this->closeApprove();
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
        $this->flash = null;

        if (!$this->rejectReason) {
            $this->error = 'Please provide a rejection reason.';
            return;
        }

        $response = Api::post('/timesheet/pr/reject/l2', [
            'timesheet_id' => $this->rejectId,
            'reason' => $this->rejectReason,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to reject timesheet.';
            return;
        }

        $this->flash = 'Timesheet rejected.';
        $this->closeReject();
        $this->closeView();
        $this->load();
    }

    public function render()
    {
        return view('livewire.director.pending-l2')
            ->layout('layouts.portal', ['title' => 'Pending L2', 'breadcrumb' => 'Pending L2']);
    }
}