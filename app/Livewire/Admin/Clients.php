<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Livewire\Component;

class Clients extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $search = '';
    public string $statusFilter = '';
    public string $activeFilter = '';
    public int $page = 1;

    public array $clients = [];
    public array $pagination = [];

    // view modal
    public bool $showView = false;
    public ?array $viewClient = null;

    // create modal
    public bool $showCreate = false;
    public array $createForm = [];

    // edit modal
    public bool $showEdit = false;
    public ?int $editId = null;
    public array $editForm = [];

    // deactivate modal — requires admin password
    public bool $showDeactivate = false;
    public ?int $deactivateId = null;
    public string $deactivateName = '';
    public string $deactivatePassword = '';

    // add manager modal
    public bool $showAddManager = false;
    public ?int $managerClientId = null;
    public array $managerForm = [];

    public function mount(): void
    {
        $this->resetCreateForm();
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['per_page' => 20];

        if ($this->search !== '')       $params['search'] = $this->search;
        if ($this->statusFilter !== '') $params['status'] = $this->statusFilter;
        if ($this->activeFilter !== '') $params['is_active'] = $this->activeFilter;
        if ($this->page > 1)            $params['page'] = $this->page;

        $response = Api::get('/client/list', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load clients.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $this->clients = $paginator['data'] ?? [];
        $this->pagination = [
            'current_page' => $paginator['current_page'] ?? 1,
            'last_page' => $paginator['last_page'] ?? 1,
            'total' => $paginator['total'] ?? 0,
        ];

        $this->loading = false;
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        $this->load();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->activeFilter = '';
        $this->page = 1;
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    // ============ create ============

    public function resetCreateForm(): void
    {
        $this->createForm = [
            'company_name' => '',
            'registration_number' => '',
            'vat_number' => '',
            'billing_address' => '',
            'primary_contact_name' => '',
            'primary_contact_email' => '',
            'primary_contact_phone' => '',
            'contract_value' => '',
            'payment_terms' => '30 days',
        ];
    }

    public function openCreate(): void
    {
        $this->resetCreateForm();
        $this->error = null;
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
    }

    public function submitCreate(): void
    {
        $this->error = null;
        $this->flash = null;

        $required = ['company_name', 'registration_number', 'primary_contact_name', 'primary_contact_email', 'primary_contact_phone'];
        foreach ($required as $field) {
            if (empty($this->createForm[$field])) {
                $this->error = 'Company name, registration number, and primary contact fields are required.';
                return;
            }
        }

        $payload = array_filter(
            $this->createForm,
            fn ($v) => $v !== '' && $v !== null
        );

        $response = Api::post('/client/create', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to create client.');
            return;
        }

        $this->flash = 'Client created.';
        $this->closeCreate();
        $this->load();
    }

    // ============ view ============

    public function openView(int $id): void
    {
        $client = collect($this->clients)->firstWhere('id', $id);

        if (!$client) {
            $this->error = 'Client not found in the current view.';
            return;
        }

        $this->viewClient = $client;
        $this->error = null;
        $this->showView = true;
    }

    public function closeView(): void
    {
        $this->showView = false;
        $this->viewClient = null;
    }

    // ============ edit ============

    public function openEdit(int $id): void
    {
        $client = collect($this->clients)->firstWhere('id', $id);

        if (!$client) {
            $this->error = 'Client not found in the current view.';
            return;
        }

        $this->editId = $id;
        $this->editForm = [
            'company_name' => $client['company_name'] ?? '',
            'registration_number' => $client['registration_number'] ?? '',
            'vat_number' => $client['vat_number'] ?? '',
            'billing_address' => $client['billing_address'] ?? '',
            'primary_contact_name' => $client['primary_contact_name'] ?? '',
            'primary_contact_email' => $client['primary_contact_email'] ?? '',
            'primary_contact_phone' => $client['primary_contact_phone'] ?? '',
            'contract_value' => $client['contract_value'] ?? '',
            'payment_terms' => $client['payment_terms'] ?? '',
        ];

        $this->error = null;
        $this->showEdit = true;
        $this->closeView();
    }

    public function closeEdit(): void
    {
        $this->showEdit = false;
        $this->editId = null;
        $this->editForm = [];
    }

    public function submitEdit(): void
    {
        $this->error = null;
        if (!$this->editId) return;

        $payload = array_filter(
            $this->editForm,
            fn ($v) => $v !== '' && $v !== null
        );

        $response = Api::put('/client/update/' . $this->editId, $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to update client.');
            return;
        }

        $this->flash = 'Client updated.';
        $this->closeEdit();
        $this->load();
    }

    // ============ deactivate ============

    public function openDeactivate(int $id): void
    {
        $client = collect($this->clients)->firstWhere('id', $id);
        if (!$client) {
            $this->error = 'Client not found.';
            return;
        }

        $this->deactivateId = $id;
        $this->deactivateName = $client['company_name'] ?? '';
        $this->deactivatePassword = '';
        $this->error = null;
        $this->showDeactivate = true;
        $this->closeView();
    }

    public function closeDeactivate(): void
    {
        $this->showDeactivate = false;
        $this->deactivateId = null;
        $this->deactivateName = '';
        $this->deactivatePassword = '';
    }

    public function submitDeactivate(): void
    {
        $this->error = null;

        if (empty($this->deactivatePassword)) {
            $this->error = 'Enter your admin password to confirm.';
            return;
        }

        // verify admin's own password before the destructive action
        if (!\Illuminate\Support\Facades\Auth::validate([
            'email' => auth()->user()->email,
            'password' => $this->deactivatePassword,
        ])) {
            $this->error = 'Password is incorrect.';
            return;
        }

        $response = Api::post('/client/deactivate/' . $this->deactivateId);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to deactivate client.';
            return;
        }

        $this->flash = $this->deactivateName . ' deactivated. Associated projects archived.';
        $this->closeDeactivate();
        $this->load();
    }

    // ============ add manager ============

    public function openAddManager(int $id): void
    {
        $this->managerClientId = $id;
        $this->managerForm = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'is_primary' => false,
        ];
        $this->error = null;
        $this->showAddManager = true;
    }

    public function closeAddManager(): void
    {
        $this->showAddManager = false;
        $this->managerClientId = null;
        $this->managerForm = [];
    }

    public function submitAddManager(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->managerForm['name']) || empty($this->managerForm['email'])) {
            $this->error = 'Name and email are required.';
            return;
        }

        $payload = [
            'client_id' => $this->managerClientId,
            'name' => $this->managerForm['name'],
            'email' => $this->managerForm['email'],
            'is_primary' => (bool) $this->managerForm['is_primary'],
        ];

        if (!empty($this->managerForm['phone'])) {
            $payload['phone'] = $this->managerForm['phone'];
        }

        $response = Api::post('/client/manager/add', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to add client manager.');
            return;
        }

        $this->flash = 'Manager added.';
        $this->closeAddManager();

        // refresh the current view's client data so the manager shows immediately
        $this->load();

        if ($this->viewClient && (int) $this->viewClient['id'] === (int) $this->managerClientId) {
            $updated = collect($this->clients)->firstWhere('id', $this->managerClientId);
            if ($updated) {
                $this->viewClient = $updated;
            }
        }
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'active'    => 'success',
            'inactive'  => 'muted',
            'suspended' => 'gold',
            default     => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.admin.clients')
            ->layout('layouts.portal', ['title' => 'Clients', 'breadcrumb' => 'Clients']);
    }
}