<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Livewire\Component;

class LeaveReasons extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public array $reasons = [];
    public string $filterType = '';

    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = [];

    public bool $showDelete = false;
    public ?int $deleteId = null;

    public function mount(): void
    {
        $this->resetForm();
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $response = Api::get('/admin/leave-reasons');

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load reasons.';
            $this->loading = false;
            return;
        }

        $this->reasons = $response['data'] ?? [];
        $this->loading = false;
    }

    public function getFilteredReasonsProperty(): array
    {
        if ($this->filterType === '') return $this->reasons;

        return array_values(array_filter(
            $this->reasons,
            fn ($r) => ($r['leave_type'] ?? '') === $this->filterType
        ));
    }

    public function resetForm(): void
    {
        $this->form = [
            'leave_type' => 'annual',
            'label' => '',
            'sort_order' => 100,
            'is_active' => true,
            'is_other' => false,
        ];
        $this->editingId = null;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $row = collect($this->reasons)->firstWhere('id', $id);
        if (! $row) return;

        $this->editingId = $id;
        $this->form = [
            'leave_type' => $row['leave_type'],
            'label' => $row['label'],
            'sort_order' => (int) $row['sort_order'],
            'is_active' => (bool) $row['is_active'],
            'is_other' => (bool) $row['is_other'],
        ];
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->error = null;

        if ($this->editingId) {
            $response = Api::put('/admin/leave-reasons/' . $this->editingId, [
                'label' => $this->form['label'],
                'sort_order' => (int) $this->form['sort_order'],
                'is_active' => (bool) $this->form['is_active'],
                'is_other' => (bool) $this->form['is_other'],
            ]);
        } else {
            $response = Api::post('/admin/leave-reasons', $this->form);
        }

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first() ?: ($response['message'] ?? 'Failed.');
            return;
        }

        $this->flash = $this->editingId ? 'Reason updated.' : 'Reason created.';
        $this->closeForm();
        $this->load();
    }

    public function openDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->showDelete = true;
    }

    public function closeDelete(): void
    {
        $this->showDelete = false;
        $this->deleteId = null;
    }

    public function destroy(): void
    {
        if (! $this->deleteId) return;

        $response = Api::delete('/admin/leave-reasons/' . $this->deleteId);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed.';
            $this->closeDelete();
            return;
        }

        $this->flash = 'Reason deleted.';
        $this->closeDelete();
        $this->load();
    }

    public function render()
    {
        return view('livewire.admin.leave-reasons')
            ->layout('layouts.portal', ['title' => 'Leave Reasons', 'breadcrumb' => 'Leave Reasons']);
    }
}