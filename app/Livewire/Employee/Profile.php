<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Livewire\Component;
use Livewire\WithFileUploads;

class Profile extends Component
{
    use WithFileUploads;

    public bool    $loading = true;
    public ?string $error   = null;
    public ?string $flash   = null;

    public ?array $profile  = null;
    public array  $contract = [];
    public array  $employment = [];

    public $photo = null;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error   = null;

        $user = auth()->user();
        $response = Api::get('/employee/profile/' . $user->id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load profile.';
            $this->loading = false;
            return;
        }

        $this->profile = $response['data'] ?? null;

        $this->employment = [
            ['Employment Type',   $this->profile['service_type'] ?? '—'],
            ['Date of Joining',   $this->fmtDate($this->profile['hire_date'] ?? null)],
            ['Employment Status', ($this->profile['is_active'] ?? false) ? 'Active' : 'Inactive'],
            ['Department',        $this->profile['department'] ?? '—'],
        ];

        // Contract info is not in the profile endpoint yet — placeholder until F7b
        $this->contract = [
            ['Contract Type',      'Permanent'],
            ['Contract Start Date', $this->fmtDate($this->profile['hire_date'] ?? null)],
            ['Contract End Date',  '—'],
            ['Notice Period',      '1 Month'],
            ['Working Hours',      '08h30 - 17h00'],
        ];

        $this->loading = false;
    }

    protected function fmtDate(?string $date): string
    {
        if (!$date) {
            return '—';
        }
        return \Carbon\Carbon::parse($date)->format('d M Y');
    }

    public function uploadPhoto(): void
    {
        if (!$this->photo) {
            return;
        }

        $response = Api::upload('/employee/upload-photo', [], ['photo' => $this->photo]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Photo upload failed.';
            return;
        }

        $this->flash = 'Photo updated.';
        $this->photo = null;
        $this->load();
    }

    public function render()
    {
        return view('livewire.employee.profile')
            ->layout('layouts.portal', ['title' => 'My Profile', 'breadcrumb' => 'My Profile']);
    }
}