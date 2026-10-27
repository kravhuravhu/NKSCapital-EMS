<?php

namespace App\Livewire\Director;

use App\Support\Api;
use Livewire\Component;

class OverrideApprovals extends Component
{
    public bool $loading = true;
    public bool $syncing = false;
    public ?string $error = null;
    public ?string $flash = null;
    public ?string $pdfUrl = null;

    public string $search = '';
    public string $month = '';
    public int $page = 1;

    public array $rows = [];
    public array $pagination = [];

    public bool $twoFactorEnabled = false;

    public bool $showView = false;
    public ?array $viewData = null;

    public bool $showOverride = false;
    public ?int $overrideId = null;
    public string $overrideComment = '';
    public string $overrideCode = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $this->twoFactorEnabled = auth()->user()->hasTwoFactorEnabled();

        $params = [
            'status' => 'rejected',
            'page' => $this->page,
            'per_page' => 15,
        ];

        if ($this->month !== '') {
            $params['month_year'] = $this->month . '-01';
        }

        $response = Api::get('/timesheet/pr/history', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load rejected timesheets.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $items = $paginator['data'] ?? [];

        // optional client-side search on top of the month/status filters
        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $items = array_values(array_filter($items, function ($row) use ($needle) {
                $haystack = strtolower(
                    ($row['user']['first_name'] ?? '') . ' ' .
                    ($row['user']['last_name'] ?? '') . ' ' .
                    ($row['user']['employee_number'] ?? '') . ' ' .
                    ($row['level1_rejection_reason'] ?? '') . ' ' .
                    ($row['level2_rejection_reason'] ?? '')
                );
                return str_contains($haystack, $needle);
            }));
        }

        $this->rows = $items;
        $this->pagination = [
            'current_page' => $paginator['current_page'] ?? 1,
            'last_page' => $paginator['last_page'] ?? 1,
            'total' => $paginator['total'] ?? 0,
        ];

        $this->loading = false;
    }

    public function updatedMonth(): void
    {
        $this->page = 1;
        $this->load();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
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

    public function openOverride(int $id): void
    {
        $this->error = null;

        if (!$this->twoFactorEnabled) {
            $this->error = 'Two-Factor Authentication must be enabled before you can override a rejection.';
            return;
        }

        $this->overrideId = $id;
        $this->overrideComment = '';
        $this->overrideCode = '';
        $this->showOverride = true;
    }

    public function closeOverride(): void
    {
        $this->showOverride = false;
        $this->overrideId = null;
        $this->overrideComment = '';
        $this->overrideCode = '';
    }

    public function submitOverride(): void
    {
        $this->error = null;
        $this->flash = null;
        $this->pdfUrl = null;

        // re-check 2FA in case the director disabled it in another tab
        $this->twoFactorEnabled = auth()->user()->hasTwoFactorEnabled();

        if (!$this->twoFactorEnabled) {
            $this->error = 'Two-Factor Authentication must be enabled before you can override a rejection.';
            return;
        }

        if (strlen($this->overrideCode) !== 6 || !ctype_digit($this->overrideCode)) {
            $this->error = 'Enter the 6-digit code from your authenticator app.';
            return;
        }

        if (trim($this->overrideComment) === '') {
            $this->error = 'A comment is required to document the override.';
            return;
        }

        $response = Api::post('/timesheet/pr/override/l2', [
            'timesheet_id' => $this->overrideId,
            '2fa_code' => $this->overrideCode,
            'comment' => $this->overrideComment,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to override rejection.');
            return;
        }

        $this->flash = 'Rejection overridden — timesheet approved at L2.';
        $this->pdfUrl = $response['data']['pdf_url'] ?? null;

        $this->closeOverride();
        $this->closeView();
        $this->load();
    }

    public static function rejectionSource(array $row): string
    {
        if (!empty($row['level2_rejection_reason'])) {
            return 'L2';
        }
        if (!empty($row['level1_rejection_reason'])) {
            return 'L1';
        }

        return '—';
    }

    public static function rejectionReason(array $row): string
    {
        return $row['level2_rejection_reason']
            ?? $row['level1_rejection_reason']
            ?? '—';
    }

    public function render()
    {
        return view('livewire.director.override-approvals')
            ->layout('layouts.portal', ['title' => 'Override Approvals', 'breadcrumb' => 'Override Approvals']);
    }
}