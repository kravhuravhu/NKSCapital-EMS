<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Livewire\Component;

class Roles extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public array $roles = [];
    public array $permissions = [];

    public bool $showCreate = false;
    public array $createForm = [];

    public bool $showEdit = false;
    public ?int $editId = null;
    public array $editForm = [];

    public bool $showDelete = false;
    public ?int $deleteId = null;
    public string $deleteName = '';

    public bool $showPermissions = false;
    public ?array $permRole = null;
    public array $permSelected = [];
    public string $permSearch = '';

    public function mount(): void
    {
        $this->resetCreateForm();
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $response = Api::get('/admin/roles/list');

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load roles.';
            $this->loading = false;
            return;
        }

        $this->roles = $response['data'] ?? [];

        // flatten every permission in the system for the picker
        $all = collect($this->roles)
            ->flatMap(fn ($r) => collect($r['permissions'] ?? [])->pluck('name'))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $this->permissions = $all;

        $this->loading = false;
    }

    public function resetCreateForm(): void
    {
        $this->createForm = [
            'name' => '',
            'guard_name' => 'web',
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

        if (empty($this->createForm['name'])) {
            $this->error = 'Role name is required.';
            return;
        }

        $response = Api::post('/admin/roles/create', $this->createForm);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to create role.');
            return;
        }

        $this->flash = 'Role created.';
        $this->closeCreate();
        $this->load();
    }

    public function openEdit(int $id): void
    {
        $role = collect($this->roles)->firstWhere('id', $id);
        if (!$role) {
            $this->error = 'Role not found.';
            return;
        }

        $this->editId = $id;
        $this->editForm = [
            'name' => $role['name'] ?? '',
            'guard_name' => $role['guard_name'] ?? 'web',
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

        $response = Api::put('/admin/roles/update/' . $this->editId, $this->editForm);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to update role.');
            return;
        }

        $this->flash = 'Role updated.';
        $this->closeEdit();
        $this->load();
    }

    public function openDelete(int $id, string $name): void
    {
        if (in_array($name, ['super_admin', 'admin', 'employee'], true)) {
            $this->error = 'System roles cannot be deleted.';
            return;
        }

        $this->deleteId = $id;
        $this->deleteName = $name;
        $this->showDelete = true;
    }

    public function closeDelete(): void
    {
        $this->showDelete = false;
        $this->deleteId = null;
        $this->deleteName = '';
    }

    public function submitDelete(): void
    {
        $this->error = null;
        if (!$this->deleteId) return;

        $response = Api::delete('/admin/roles/delete/' . $this->deleteId);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to delete role.';
            $this->closeDelete();
            return;
        }

        $this->flash = 'Role deleted.';
        $this->closeDelete();
        $this->load();
    }

    public function openPermissions(int $id): void
    {
        $role = collect($this->roles)->firstWhere('id', $id);
        if (!$role) {
            $this->error = 'Role not found.';
            return;
        }

        $this->permRole = [
            'id' => $role['id'],
            'name' => $role['name'],
        ];
        $this->permSelected = collect($role['permissions'] ?? [])
            ->pluck('name')
            ->values()
            ->toArray();
        $this->permSearch = '';
        $this->error = null;
        $this->showPermissions = true;
    }

    public function closePermissions(): void
    {
        $this->showPermissions = false;
        $this->permRole = null;
        $this->permSelected = [];
        $this->permSearch = '';
    }

    public function togglePermission(string $name): void
    {
        if (in_array($name, $this->permSelected, true)) {
            $this->permSelected = array_values(array_diff($this->permSelected, [$name]));
        } else {
            $this->permSelected[] = $name;
        }
    }

    public function submitPermissions(): void
    {
        $this->error = null;
        if (!$this->permRole) return;

        $response = Api::post('/admin/roles/permissions/assign', [
            'role' => $this->permRole['name'],
            'permissions' => $this->permSelected,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to save permissions.');
            return;
        }

        $this->flash = 'Permissions updated for ' . $this->permRole['name'] . '.';
        $this->closePermissions();
        $this->load();
    }

    public function getFilteredPermissionsProperty(): array
    {
        if ($this->permSearch === '') {
            return $this->permissions;
        }

        $needle = strtolower($this->permSearch);

        return array_values(array_filter(
            $this->permissions,
            fn ($p) => str_contains(strtolower($p), $needle)
        ));
    }

    public static function roleTone(string $name): string
    {
        return match ($name) {
            'super_admin' => 'red',
            'admin'       => 'gold',
            'director'    => 'purple',
            'manager'     => 'blue',
            'recruiter'   => 'gold',
            'employee'    => 'muted',
            default       => 'muted',
        };
    }

    public function isSystemRole(string $name): bool
    {
        return in_array($name, ['super_admin', 'admin', 'employee'], true);
    }

    public function render()
    {
        return view('livewire.admin.roles')
            ->layout('layouts.portal', ['title' => 'Roles', 'breadcrumb' => 'Roles']);
    }
}