<?php

namespace App\Livewire\Concerns;

use App\Support\Api;

trait HandlesOnboarding
{
    public bool $showOnboarding = false;
    public ?int $onboardingUserId = null;
    public string $onboardingName = '';
    public array $onboardingItems = [];
    public int $onboardingProgress = 0;
    public ?string $onboardingError = null;
    public bool $onboardingPendingEmail = false;

    public function openOnboarding(int $id): void
    {
        $this->onboardingUserId = $id;
        $this->onboardingError = null;
        $this->showOnboarding = true;

        if (method_exists($this, 'closeView')) {
            $this->closeView();
        }
        if (method_exists($this, 'closeDetail')) {
            $this->closeDetail();
        }

        $this->loadOnboarding();
    }

    public function closeOnboarding(): void
    {
        $this->showOnboarding = false;
        $this->onboardingUserId = null;
        $this->onboardingName = '';
        $this->onboardingItems = [];
        $this->onboardingProgress = 0;
        $this->onboardingError = null;
        $this->onboardingPendingEmail = false;
    }

    protected function loadOnboarding(): void
    {
        if (! $this->onboardingUserId) return;

        $response = Api::get('/employee/' . $this->onboardingUserId . '/onboarding');

        if (! $response['ok']) {
            $this->onboardingError = $response['message'] ?? 'Unable to load onboarding checklist.';
            return;
        }

        $this->onboardingName = $response['data']['name'] ?? '';
        $this->onboardingItems = $response['data']['items'] ?? [];
        $this->onboardingProgress = (int) ($response['data']['progress'] ?? 0);
        $this->onboardingPendingEmail = (bool) ($response['data']['pending_company_email'] ?? false);
    }

    public function setOnboardingStatus(string $itemKey, string $status): void
    {
        if (! $this->onboardingUserId) return;

        $this->onboardingError = null;

        $response = Api::put('/employee/' . $this->onboardingUserId . '/onboarding', [
            'item_key' => $itemKey,
            'status' => $status,
        ]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->onboardingError = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to update item.');
            return;
        }

        $this->loadOnboarding();
    }
}