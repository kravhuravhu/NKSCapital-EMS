@props(['user', 'title', 'breadcrumb'])

@php
    $initials = strtoupper(
        substr($user->first_name ?? 'U', 0, 1) . substr($user->last_name ?? '', 0, 1)
    );
    $roleFallback = match ($user->role) {
        'super_admin' => 'Super Admin',
        'admin' => 'Administrator',
        'director' => 'Director',
        'manager' => 'Manager',
        'recruiter' => 'Recruiter',
        default => 'Employee',
    };
    $subtitle = $user->position ?: $roleFallback;
    $unread = (int) (\App\Models\Notification::where('user_id', $user->id)
        ->where('is_read', false)
        ->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })
        ->count());
    $breadcrumbTrail = array_filter([
        ['label' => 'NKS Capital', 'url' => route('portal.dashboard')],
        ['label' => $breadcrumb ?? $title ?? 'Dashboard', 'url' => null],
    ]);
@endphp

<header class="nks-topbar">

    <div class="nks-topbar-heading">
        <h1>{{ $title ?? 'Dashboard' }}</h1>
        <nav class="nks-topbar-breadcrumb" aria-label="Breadcrumb">
            @foreach ($breadcrumbTrail as $crumb)
                @if ($crumb['url'])
                    <a href="{{ $crumb['url'] }}" wire:navigate>{{ $crumb['label'] }}</a>
                @else
                    <strong>{{ $crumb['label'] }}</strong>
                @endif
                @if (! $loop->last)
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                @endif
            @endforeach
        </nav>
    </div>

    <div class="nks-topbar-search">
        @livewire('shared.global-search')
    </div>

    <div class="nks-topbar-actions">
        <button type="button"
                class="nks-icon-btn"
                onclick="nksToggleTheme()"
                title="Toggle theme">
            <i class="fa-solid fa-moon" id="nks-theme-icon" aria-hidden="true"></i>
        </button>

        @if (in_array($user->role, ['employee', 'manager', 'director', 'admin', 'super_admin'], true))
            <a href="{{ route('portal.employee.notifications') }}"
               class="nks-icon-btn"
               aria-label="Notifications"
               wire:navigate>
                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                @if ($unread > 0)
                    <span class="nks-dot" aria-hidden="true"></span>
                @endif
            </a>
        @endif

        <div class="nks-user-menu-wrap"
             x-data="{ open: false }"
             @click.outside="open = false"
             style="position: relative;">
            <button type="button"
                    class="nks-user-menu"
                    @click="open = ! open"
                    :aria-expanded="open.toString()">
                <span class="nks-user-avatar">{{ $initials }}</span>
                <span class="nks-user-info">
                    <strong>{{ $user->full_name ?? $user->email }}</strong>
                    <small>{{ $subtitle }}</small>
                </span>
                <i class="fa-solid fa-chevron-down"
                   style="font-size: 10px; color: var(--nks-muted);"
                   aria-hidden="true"></i>
            </button>

            <div class="nks-user-dropdown"
                 x-show="open"
                 x-cloak
                 x-transition.origin.top.right>
                <div class="nks-user-dropdown-head">
                    <strong>{{ $user->full_name ?? $user->email }}</strong>
                    <small>{{ $user->email }}</small>
                </div>
                <div class="nks-user-dropdown-body">
                    <a href="{{ route('portal.employee.profile') }}" class="nks-user-dropdown-item" wire:navigate>
                        <i class="fa-solid fa-user" aria-hidden="true"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="{{ route('portal.employee.security') }}" class="nks-user-dropdown-item" wire:navigate>
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        <span>Security</span>
                    </a>
                </div>
                <div class="nks-user-dropdown-foot">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="nks-user-dropdown-item nks-user-dropdown-item--danger">
                            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                            <span>Sign out</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>