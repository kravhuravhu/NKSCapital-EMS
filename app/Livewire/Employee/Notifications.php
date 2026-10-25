<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;

class Notifications extends Component
{
    public bool    $loading = true;
    public ?string $error   = null;
    public ?string $flash   = null;

    public array $groups      = [];
    public array $preferences = [];
    public int   $unreadCount = 0;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error   = null;

        $list = Api::get('/notifications/list', ['per_page' => 60]);

        if (!$list['ok']) {
            $this->error = $list['message'] ?? 'Unable to load notifications.';
            $this->loading = false;
            return;
        }

        $items = $list['data']['notifications']['data'] ?? [];
        $this->unreadCount = (int) ($list['data']['unread_count'] ?? 0);

        $this->groups = $this->groupByDate($items);

        $prefs = Api::get('/notifications/preferences');

        if ($prefs['ok']) {
            $this->preferences = $prefs['data'] ?? [];
        }

        $this->loading = false;
    }

    protected function groupByDate(array $items): array
    {
        $today     = Carbon::today();
        $yesterday = Carbon::yesterday();

        $groups = [
            'today'     => ['label' => 'Today',     'items' => []],
            'yesterday' => ['label' => 'Yesterday', 'items' => []],
            'earlier'   => ['label' => 'Earlier',   'items' => []],
        ];

        foreach ($items as $item) {
            $date = isset($item['created_at'])
                ? Carbon::parse($item['created_at'])->startOfDay()
                : null;

            if ($date && $date->equalTo($today)) {
                $groups['today']['items'][] = $item;
            } elseif ($date && $date->equalTo($yesterday)) {
                $groups['yesterday']['items'][] = $item;
            } else {
                $groups['earlier']['items'][] = $item;
            }
        }

        return array_values(array_filter($groups, fn ($g) => count($g['items']) > 0));
    }

    // ============ actions ============

    public function markRead(int $notificationId): void
    {
        $response = Api::post('/notifications/mark-read', [
            'notification_id' => $notificationId,
        ]);

        if ($response['ok']) {
            $this->load();
        }
    }

    public function markAllRead(): void
    {
        $response = Api::post('/notifications/mark-all-read');

        if ($response['ok']) {
            $this->flash = $response['message'] ?? 'All notifications marked as read.';
            $this->load();
        }
    }

    public function deleteNotification(int $notificationId): void
    {
        $response = Api::delete('/notifications/delete/' . $notificationId);

        if ($response['ok']) {
            $this->load();
        }
    }

    public function togglePreference(string $category, string $field): void
    {
        if (!in_array($field, ['in_app', 'email', 'digest_only'], true)) {
            return;
        }

        // find existing preference
        $updated = collect($this->preferences)->map(function ($p) use ($category, $field) {
            if (($p['category'] ?? null) === $category) {
                $p[$field] = !(bool) ($p[$field] ?? false);
            }
            return $p;
        })->toArray();

        $response = Api::put('/notifications/preferences/update', [
            'preferences' => collect($updated)->map(fn ($p) => [
                'category'    => $p['category'],
                'in_app'      => (bool) ($p['in_app'] ?? false),
                'email'       => (bool) ($p['email'] ?? false),
                'digest_only' => (bool) ($p['digest_only'] ?? false),
            ])->toArray(),
        ]);

        if ($response['ok']) {
            $this->preferences = $response['data'] ?? $updated;
            $this->flash = 'Preferences saved.';
        } else {
            $this->error = $response['message'] ?? 'Could not save preferences.';
        }
    }

    public static function iconForType(?string $type): string
    {
        $type = (string) $type;

        if (str_contains($type, 'TIMESHEET'))   return 'fa-solid fa-clock';
        if (str_contains($type, 'LEAVE'))       return 'fa-solid fa-umbrella-beach';
        if (str_contains($type, 'ASSET'))       return 'fa-solid fa-laptop';
        if (str_contains($type, 'CONTRACT'))    return 'fa-solid fa-file-contract';
        if (str_contains($type, 'PAYROLL'))     return 'fa-solid fa-money-check-dollar';
        if (str_contains($type, 'MEETING'))     return 'fa-solid fa-users';
        if (str_contains($type, 'DELEGATION'))  return 'fa-solid fa-user-cog';
        if (str_contains($type, 'RECRUIT'))     return 'fa-solid fa-briefcase';

        return 'fa-solid fa-bell';
    }

    public static function prettyTime(?string $iso): string
    {
        if (!$iso) {
            return '';
        }
        return Carbon::parse($iso)->diffForHumans();
    }

    public function render()
    {
        return view('livewire.employee.notifications')
            ->layout('layouts.portal', ['title' => 'Notifications', 'breadcrumb' => 'Notifications']);
    }
}