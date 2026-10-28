<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Livewire\Component;
use Livewire\WithFileUploads;

class ClientTemplates extends Component
{
    use WithFileUploads;

    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $search = '';
    public string $activeFilter = '1';
    public int $page = 1;

    public array $templates = [];
    public array $pagination = [];
    public array $clients = [];

    // upload modal
    public bool $showUpload = false;
    public array $uploadForm = [];
    public $uploadFile = null;

    // update modal
    public bool $showUpdate = false;
    public ?int $updateId = null;
    public array $updateForm = [];
    public $updateFile = null;

    // view modal
    public bool $showView = false;
    public ?array $viewTemplate = null;

    public function mount(): void
    {
        $this->resetUploadForm();
        $this->loadClients();
        $this->load();
    }

    public function loadClients(): void
    {
        $response = Api::get('/client/list', ['is_active' => 1, 'per_page' => 200]);

        if ($response['ok']) {
            $this->clients = $response['data']['data'] ?? [];
        } else {
            $this->clients = [];
            $this->error = $response['message'] ?? 'Unable to load clients for the template picker.';
        }
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['per_page' => 20];

        if ($this->search !== '')       $params['search'] = $this->search;
        if ($this->activeFilter !== '') $params['is_active'] = $this->activeFilter;
        if ($this->page > 1)            $params['page'] = $this->page;

        $response = Api::get('/client/template/list', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load templates.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $this->templates = $paginator['data'] ?? [];
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
        $this->activeFilter = '1';
        $this->page = 1;
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    // ============ upload ============

    public function resetUploadForm(): void
    {
        $this->uploadForm = [
            'client_id' => '',
            'template_name' => '',
            'template_content' => '',
        ];
        $this->uploadFile = null;
    }

    public function openUpload(): void
    {
        $this->resetUploadForm();
        $this->error = null;
        $this->showUpload = true;
    }

    public function closeUpload(): void
    {
        $this->showUpload = false;
        $this->resetUploadForm();
    }

    public function submitUpload(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->uploadForm['client_id'])) {
            $this->error = 'Select a client.';
            return;
        }
        if (empty($this->uploadForm['template_name'])) {
            $this->error = 'Template name is required.';
            return;
        }
        if (!$this->uploadFile) {
            $this->error = 'Select a PDF or Word file to upload.';
            return;
        }

        // verify the selected client still exists in the loaded list — stale ids cannot slip through
        if (!collect($this->clients)->contains('id', (int) $this->uploadForm['client_id'])) {
            $this->error = 'The selected client no longer exists. Refresh and try again.';
            $this->loadClients();
            return;
        }

        $payload = [
            'client_id' => $this->uploadForm['client_id'],
            'template_name' => $this->uploadForm['template_name'],
        ];
        if (!empty($this->uploadForm['template_content'])) {
            $payload['template_content'] = $this->uploadForm['template_content'];
        }

        $response = Api::upload('/client/template/upload', $payload, [
            'template_file' => $this->uploadFile,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to upload template.');
            return;
        }

        $this->flash = 'Template uploaded.';
        $this->closeUpload();
        $this->load();
    }

    // ============ update ============

    public function openUpdate(int $id): void
    {
        $row = collect($this->templates)->firstWhere('id', $id);

        if (!$row) {
            $this->error = 'Template not found, it may have been removed. Refresh and try again.';
            return;
        }

        $this->updateId = $id;
        $this->updateForm = [
            'template_name' => $row['template_name'] ?? '',
            'template_content' => $row['template_content'] ?? '',
        ];
        $this->updateFile = null;
        $this->error = null;
        $this->showUpdate = true;
        $this->closeView();
    }

    public function closeUpdate(): void
    {
        $this->showUpdate = false;
        $this->updateId = null;
        $this->updateForm = [];
        $this->updateFile = null;
    }

    public function submitUpdate(): void
    {
        $this->error = null;
        if (!$this->updateId) return;

        $payload = array_filter(
            $this->updateForm,
            fn ($v) => $v !== '' && $v !== null
        );

        $files = $this->updateFile ? ['template_file' => $this->updateFile] : [];

        if (!empty($files)) {
            $response = Api::upload('/client/template/update/' . $this->updateId, $payload, $files);
        } else {
            $response = Api::put('/client/template/update/' . $this->updateId, $payload);
        }

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to update template.');
            return;
        }

        $this->flash = 'Template updated.';
        $this->closeUpdate();
        $this->load();
    }

    // ============ view ============

    public function openView(int $id): void
    {
        $row = collect($this->templates)->firstWhere('id', $id);

        if (!$row) {
            $this->error = 'Template not found.';
            return;
        }

        $this->viewTemplate = $row;
        $this->error = null;
        $this->showView = true;
    }

    public function closeView(): void
    {
        $this->showView = false;
        $this->viewTemplate = null;
    }

    public static function activeTone(bool $active): string
    {
        return $active ? 'success' : 'muted';
    }

    public function render()
    {
        return view('livewire.admin.client-templates')
            ->layout('layouts.portal', ['title' => 'Client Templates', 'breadcrumb' => 'Client Templates']);
    }
}