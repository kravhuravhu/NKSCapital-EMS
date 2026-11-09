<?php

namespace App\Livewire\Shared;

use App\Support\Api;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';
    public array $groups = [];
    public bool $open = false;
    public bool $searching = false;

    public function updatedQuery(): void
    {
        $this->searching = true;

        if (strlen(trim($this->query)) < 2) {
            $this->groups = [];
            $this->open = false;
            $this->searching = false;
            return;
        }

        $response = Api::get('/search', ['q' => $this->query]);

        $this->groups = $response['ok'] ? ($response['data']['groups'] ?? []) : [];
        $this->open = true;
        $this->searching = false;
    }

    public function close(): void
    {
        $this->open = false;
        $this->query = '';
        $this->groups = [];
    }

    public function render()
    {
        return view('livewire.shared.global-search');
    }
}