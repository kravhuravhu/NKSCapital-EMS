<?php

namespace App\Livewire\Director;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;

class Delegations extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $view = 'mine';
    public string $status = 'active';
    public int $page = 1;

    public array $delegations = [];
    public array $pagination = [];
    public array $stats = ['active' => 0, 'upcoming' => 0, 'expired' => 0, 'revoked' => 0];

    public array $delegates = [];
    public array $approvers = [];

    public bool $showCreate = false;
    public array $createForm = [];

    public bool $showRevoke = false;
    public ?int $revokeId = null;
    public string $revokeReason = '';

    public bool $showView = false;
    public ?array $viewData = null;

    public function mount(): void
    {
        $this->resetCreateForm();
        $this->loadStats();
        $this->load();
    }

    public function resetCreateForm(): void
    {
        $this->createForm = [
            'original_approver_id' => auth()->id(),
            'delegate_id' => '',
            'delegation_type' => 'both',
            'title' => '',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'reason' => '',
            'auto_activate' => true,
        ];
    }

    public function loadStats(): void
    {
        $params = ['status' => 'all', 'per_page' => 100];
        $this->applyViewParam($params);

        $response = Api::get('/admin/delegation/list', $params);

        if (!$response['ok']) {
            return;
        }

        $items = $response['data']['data'] ?? [];
        $now = now();

        $active = 0;
        $upcoming = 0;
        $expired = 0;
        $revoked = 0;

        foreach ($items as $d) {
            if (!empty($d['revoked_at'])) {
                $revoked++;
                continue;
            }

            $start = Carbon::parse($d['start_date'] ?? now());
            $end = Carbon::parse($d['end_date'] ?? now());

            if ($end->lt($now)) {
                $expired++;
            } elseif ($start->gt($now)) {
                $upcoming++;
            } elseif (!empty($d['is_active'])) {
                $active++;
            }
        }

        $this->stats = compact('active', 'upcoming', 'expired', 'revoked');
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['per_page' => 15];
        $this->applyViewParam($params);

        // revoked tab: fetch everything and filter client-side since the API does not scope on it
        $clientFilterRevoked = false;

        if ($this->status === 'revoked') {
            $params['status'] = 'all';
            $params['per_page'] = 100;
            $clientFilterRevoked = true;
        } elseif ($this->status !== 'all') {
            $params['status'] = $this->status;
            $params['page'] = $this->page;
        } else {
            $params['status'] = 'all';
            $params['page'] = $this->page;
        }

        $response = Api::get('/admin/delegation/list', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load delegations.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $items = $paginator['data'] ?? [];

        if ($clientFilterRevoked) {
            $items = array_values(array_filter(
                $items,
                fn ($d) => !empty($d['revoked_at'])
            ));
        }

        $this->delegations = $items;
        $this->pagination = [
            'current_page' => $clientFilterRevoked ? 1 : ($paginator['current_page'] ?? 1),
            'last_page' => $clientFilterRevoked ? 1 : ($paginator['last_page'] ?? 1),
            'total' => $clientFilterRevoked ? count($items) : ($paginator['total'] ?? 0),
        ];

        $this->loading = false;
    }

    protected function applyViewParam(array &$params): void
    {
        if ($this->view === 'mine') {
            $params['original_approver_id'] = auth()->id();
        } elseif ($this->view === 'received') {
            $params['delegate_id'] = auth()->id();
        }
        // 'all' adds no filter
    }

    public function selectView(string $view): void
    {
        if (!in_array($view, ['mine', 'received', 'all'], true)) {
            return;
        }

        $this->view = $view;
        $this->page = 1;
        $this->loadStats();
        $this->load();
    }

    public function selectStatus(string $status): void
    {
        if (!in_array($status, ['active', 'upcoming', 'expired', 'revoked', 'all'], true)) {
            return;
        }

        $this->status = $status;
        $this->page = 1;
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    public function openCreate(): void
    {
        $this->resetCreateForm();
        $this->error = null;
        $this->loadEligibleUsers();
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
    }

    protected function loadEligibleUsers(): void
    {
        // delegate must have manager, director, or admin role per DelegationService validation
        $response = Api::get('/employee/list', [
            'is_active' => 1,
            'per_page' => 100,
        ]);

        $users = $response['ok'] ? ($response['data']['data'] ?? []) : [];

        $eligible = array_values(array_filter(
            $users,
            fn ($u) => in_array($u['role'] ?? '', ['manager', 'director', 'admin'], true)
        ));

        $this->delegates = $eligible;
        $this->approvers = $eligible;
    }

    public function submitCreate(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->createForm['delegate_id'])) {
            $this->error = 'Select a delegate.';
            return;
        }

        if ($this->createForm['delegate_id'] == $this->createForm['original_approver_id']) {
            $this->error = 'You cannot delegate to yourself.';
            return;
        }

        if (empty($this->createForm['start_date']) || empty($this->createForm['end_date'])) {
            $this->error = 'Set both the start and end dates.';
            return;
        }

        if (Carbon::parse($this->createForm['end_date'])->lte(Carbon::parse($this->createForm['start_date']))) {
            $this->error = 'End date must be after the start date.';
            return;
        }

        $modules = match ($this->createForm['delegation_type']) {
            'both' => ['all'],
            'timesheet' => ['timesheet'],
            'leave' => ['leave'],
            default => ['all'],
        };

        $payload = [
            'original_approver_id' => $this->createForm['original_approver_id'] ?: auth()->id(),
            'delegate_id' => $this->createForm['delegate_id'],
            'delegation_type' => $this->createForm['delegation_type'],
            'delegation_modules' => $modules,
            'start_date' => $this->createForm['start_date'],
            'end_date' => $this->createForm['end_date'],
            'is_active' => true,
            'auto_activate' => (bool) $this->createForm['auto_activate'],
        ];

        if (!empty($this->createForm['title'])) {
            $payload['title'] = $this->createForm['title'];
        }
        if (!empty($this->createForm['reason'])) {
            $payload['reason'] = $this->createForm['reason'];
        }

        $response = Api::post('/admin/delegation/create', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to create delegation.');
            return;
        }

        $this->flash = 'Delegation created.';
        $this->closeCreate();
        $this->loadStats();
        $this->load();
    }

    public function openRevoke(int $id): void
    {
        $this->revokeId = $id;
        $this->revokeReason = '';
        $this->showRevoke = true;
    }

    public function closeRevoke(): void
    {
        $this->showRevoke = false;
        $this->revokeId = null;
        $this->revokeReason = '';
    }

    public function submitRevoke(): void
    {
        $this->error = null;
        $this->flash = null;

        if (!$this->revokeReason) {
            $this->error = 'Please provide a revocation reason.';
            return;
        }

        $response = Api::post('/admin/delegation/revoke', [
            'delegation_id' => $this->revokeId,
            'reason' => $this->revokeReason,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to revoke delegation.');
            return;
        }

        $this->flash = 'Delegation revoked.';
        $this->closeRevoke();
        $this->closeView();
        $this->loadStats();
        $this->load();
    }

    public function activate(int $id): void
    {
        $this->error = null;
        $this->flash = null;

        $response = Api::post('/admin/delegation/activate/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to activate delegation.';
            return;
        }

        $this->flash = 'Delegation activated.';
        $this->loadStats();
        $this->load();
    }

    public function view(int $id): void
    {
        $row = collect($this->delegations)->firstWhere('id', $id);

        if (!$row) {
            $this->error = 'Delegation not found in the current view.';
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

    public static function statusOf(array $d): string
    {
        if (!empty($d['revoked_at'])) {
            return 'revoked';
        }

        $start = Carbon::parse($d['start_date'] ?? now());
        $end = Carbon::parse($d['end_date'] ?? now());

        if ($end->lt(now())) {
            return 'expired';
        }
        if ($start->gt(now())) {
            return 'upcoming';
        }

        return !empty($d['is_active']) ? 'active' : 'inactive';
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'upcoming' => 'blue',
            'expired' => 'muted',
            'revoked' => 'red',
            default => 'muted',
        };
    }

    public function canRevoke(array $d): bool
    {
        return empty($d['revoked_at']);
    }

    public function canActivate(array $d): bool
    {
        if (!empty($d['revoked_at'])) return false;
        if (!empty($d['is_active'])) return false;

        $start = Carbon::parse($d['start_date'] ?? now());
        $end = Carbon::parse($d['end_date'] ?? now());

        return $start->lte(now()) && $end->gte(now());
    }

    public function render()
    {
        return view('livewire.director.delegations')
            ->layout('layouts.portal', ['title' => 'Delegations', 'breadcrumb' => 'Delegations']);
    }
}