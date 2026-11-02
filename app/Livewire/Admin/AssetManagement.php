<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class AssetManagement extends Component
{
    use WithFileUploads;

    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $search = '';
    public string $statusFilter = '';
    public string $conditionFilter = '';
    public bool $overdueOnly = false;
    public int $page = 1;

    public array $assets = [];
    public array $pagination = [];
    public array $stats = [];
    public array $employees = [];

    // register modal
    public bool $showRegister = false;
    public array $registerForm = [];

    // loan modal
    public bool $showLoan = false;
    public ?int $loanAssetId = null;
    public string $loanAssetLabel = '';
    public array $loanForm = [];

    // return modal
    public bool $showReturn = false;
    public ?int $returnAssetId = null;
    public string $returnAssetLabel = '';
    public array $returnForm = [];
    public $returnPhoto = null;

    // edit modal
    public bool $showEdit = false;
    public ?int $editId = null;
    public array $editForm = [];

    // retire modal — password confirmation
    public bool $showRetire = false;
    public ?int $retireId = null;
    public string $retireLabel = '';
    public string $retirePassword = '';

    // history / view modal
    public bool $showView = false;
    public ?array $viewData = null;

    public function mount(): void
    {
        $this->resetRegisterForm();
        $this->loadStats();
        $this->loadEmployees();
        $this->load();
    }

    protected function loadStats(): void
    {
        $response = Api::get('/asset/report');

        if ($response['ok']) {
            $this->stats = $response['data']['stats'] ?? [];
        }
    }

    protected function loadEmployees(): void
    {
        $response = Api::get('/employee/list', ['per_page' => 300, 'is_active' => 1]);

        if ($response['ok']) {
            $this->employees = $response['data']['data'] ?? [];
        }
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['per_page' => 20];

        if ($this->search !== '')          $params['search'] = $this->search;
        if ($this->statusFilter !== '')    $params['status'] = $this->statusFilter;
        if ($this->conditionFilter !== '') $params['condition'] = $this->conditionFilter;
        if ($this->overdueOnly)            $params['is_overdue'] = 1;
        if ($this->page > 1)               $params['page'] = $this->page;

        $response = Api::get('/asset/list', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load assets.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $this->assets = $paginator['data'] ?? [];
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
        $this->conditionFilter = '';
        $this->overdueOnly = false;
        $this->page = 1;
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    // ============ register ============

    public function resetRegisterForm(): void
    {
        $this->registerForm = [
            'asset_tag' => '',
            'serial_number' => '',
            'model' => '',
            'manufacturer' => '',
            'purchase_date' => '',
            'purchase_price' => '',
            'warranty_expiry' => '',
            'condition' => 'new',
            'location' => '',
            'notes' => '',
        ];
    }

    public function openRegister(): void
    {
        $this->resetRegisterForm();
        $this->error = null;
        $this->showRegister = true;
    }

    public function closeRegister(): void
    {
        $this->showRegister = false;
    }

    public function submitRegister(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->registerForm['asset_tag']) || empty($this->registerForm['model'])) {
            $this->error = 'Asset tag and model are required.';
            return;
        }

        $payload = array_filter(
            $this->registerForm,
            fn ($v) => $v !== '' && $v !== null
        );

        $response = Api::post('/asset/register', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to register asset.');
            return;
        }

        $this->flash = 'Asset registered.';
        $this->closeRegister();
        $this->loadStats();
        $this->load();
    }

    // ============ loan ============

    public function openLoan(int $id): void
    {
        $asset = collect($this->assets)->firstWhere('id', $id);

        if (!$asset) {
            $this->error = 'Asset not found.';
            return;
        }

        if (($asset['status'] ?? '') !== 'available') {
            $this->error = 'Only available assets can be loaned.';
            return;
        }

        $this->loanAssetId = $id;
        $this->loanAssetLabel = ($asset['asset_tag'] ?? '') . ' — ' . ($asset['model'] ?? '');
        $this->loanForm = [
            'assigned_to_id' => '',
            'checkout_date' => now()->format('Y-m-d'),
            'expected_return_date' => now()->addDays(30)->format('Y-m-d'),
            'checkout_condition' => $asset['condition'] ?? 'good',
            'location' => '',
            'notes' => '',
        ];
        $this->error = null;
        $this->showLoan = true;
    }

    public function closeLoan(): void
    {
        $this->showLoan = false;
        $this->loanAssetId = null;
        $this->loanAssetLabel = '';
        $this->loanForm = [];
    }

    public function submitLoan(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->loanForm['assigned_to_id'])) {
            $this->error = 'Select an employee to loan this asset to.';
            return;
        }

        $payload = [
            'asset_id' => $this->loanAssetId,
            'assigned_to_id' => $this->loanForm['assigned_to_id'],
            'checkout_date' => $this->loanForm['checkout_date'],
            'expected_return_date' => $this->loanForm['expected_return_date'] ?: null,
            'checkout_condition' => $this->loanForm['checkout_condition'],
        ];
        if (!empty($this->loanForm['location']))  $payload['location']  = $this->loanForm['location'];
        if (!empty($this->loanForm['notes']))     $payload['notes']     = $this->loanForm['notes'];

        $response = Api::post('/asset/loan', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to loan asset.');
            return;
        }

        $this->flash = 'Asset loaned.';
        $this->closeLoan();
        $this->loadStats();
        $this->load();
    }

    // ============ return ============

    public function openReturn(int $id): void
    {
        $asset = collect($this->assets)->firstWhere('id', $id);

        if (!$asset) {
            $this->error = 'Asset not found.';
            return;
        }

        if (($asset['status'] ?? '') !== 'loaned') {
            $this->error = 'This asset is not currently on loan.';
            return;
        }

        $this->returnAssetId = $id;
        $this->returnAssetLabel = ($asset['asset_tag'] ?? '') . ' — ' . ($asset['model'] ?? '');
        $this->returnForm = [
            'actual_return_date' => now()->format('Y-m-d'),
            'return_condition' => $asset['condition'] ?? 'good',
            'repair_priority' => '',
            'returned_to_location' => '',
            'notes' => '',
        ];
        $this->returnPhoto = null;
        $this->error = null;
        $this->showReturn = true;
    }

    public function closeReturn(): void
    {
        $this->showReturn = false;
        $this->returnAssetId = null;
        $this->returnAssetLabel = '';
        $this->returnForm = [];
        $this->returnPhoto = null;
    }

    public function submitReturn(): void
    {
        $this->error = null;
        $this->flash = null;

        $payload = [
            'asset_id' => $this->returnAssetId,
            'actual_return_date' => $this->returnForm['actual_return_date'],
            'return_condition' => $this->returnForm['return_condition'],
        ];
        if (!empty($this->returnForm['repair_priority'])) {
            $payload['repair_priority'] = $this->returnForm['repair_priority'];
        }
        if (!empty($this->returnForm['returned_to_location'])) {
            $payload['returned_to_location'] = $this->returnForm['returned_to_location'];
        }
        if (!empty($this->returnForm['notes'])) {
            $payload['notes'] = $this->returnForm['notes'];
        }

        $files = $this->returnPhoto ? ['damage_photo' => $this->returnPhoto] : [];

        $response = $files
            ? Api::upload('/asset/return', $payload, $files)
            : Api::post('/asset/return', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to return asset.');
            return;
        }

        $this->flash = 'Asset returned.';
        $this->closeReturn();
        $this->loadStats();
        $this->load();
    }

    // ============ edit ============

    public function openEdit(int $id): void
    {
        $row = collect($this->assets)->firstWhere('id', $id);

        if (!$row) {
            $this->error = 'Asset not found.';
            return;
        }

        $this->editId = $id;
        $this->editForm = [
            'model' => $row['model'] ?? '',
            'manufacturer' => $row['manufacturer'] ?? '',
            'serial_number' => $row['serial_number'] ?? '',
            'condition' => $row['condition'] ?? 'good',
            'location' => $row['location'] ?? '',
            'notes' => $row['notes'] ?? '',
            'purchase_date' => $row['purchase_date'] ?? '',
            'purchase_price' => $row['purchase_price'] ?? '',
            'warranty_expiry' => $row['warranty_expiry'] ?? '',
        ];

        $this->error = null;
        $this->showEdit = true;
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

        $response = Api::put('/asset/update/' . $this->editId, $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to update asset.');
            return;
        }

        $this->flash = 'Asset updated.';
        $this->closeEdit();
        $this->load();
    }

    // ============ retire ============

    public function openRetire(int $id): void
    {
        $row = collect($this->assets)->firstWhere('id', $id);

        if (!$row) {
            $this->error = 'Asset not found.';
            return;
        }

        $this->retireId = $id;
        $this->retireLabel = ($row['asset_tag'] ?? '') . ' — ' . ($row['model'] ?? '');
        $this->retirePassword = '';
        $this->error = null;
        $this->showRetire = true;
    }

    public function closeRetire(): void
    {
        $this->showRetire = false;
        $this->retireId = null;
        $this->retireLabel = '';
        $this->retirePassword = '';
    }

    public function submitRetire(): void
    {
        $this->error = null;

        if (empty($this->retirePassword)) {
            $this->error = 'Enter your admin password to confirm.';
            return;
        }

        if (!Auth::validate([
            'email' => auth()->user()->email,
            'password' => $this->retirePassword,
        ])) {
            $this->error = 'Password is incorrect.';
            return;
        }

        $response = Api::post('/asset/retire/' . $this->retireId);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to retire asset.';
            return;
        }

        $this->flash = 'Asset retired.';
        $this->closeRetire();
        $this->loadStats();
        $this->load();
    }

    // ============ view ============

    public function openView(int $id): void
    {
        $this->error = null;

        $response = Api::get('/asset/history/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load asset history.';
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

    // ============ helpers ============

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'available'         => 'success',
            'loaned'            => 'blue',
            'repair_requested',
            'maintenance'       => 'gold',
            'lost', 'damaged'   => 'red',
            'retired'           => 'muted',
            default             => 'muted',
        };
    }

    public static function conditionTone(string $condition): string
    {
        return match ($condition) {
            'new', 'good', 'refurbished' => 'success',
            'fair'                       => 'blue',
            'poor', 'for_repair'         => 'gold',
            'damaged', 'obsolete'        => 'red',
            default                      => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.admin.asset-management')
            ->layout('layouts.portal', ['title' => 'Assets', 'breadcrumb' => 'Assets']);
    }
}