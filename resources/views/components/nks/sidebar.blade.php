@props(['user'])

@php
    $userRoles = $user->getRoleNames()->toArray();

    if (! empty($user->role) && ! in_array($user->role, $userRoles, true)) {
        $userRoles[] = $user->role;
    }

    if (in_array('operational_manager', $userRoles, true) && ! in_array('director', $userRoles, true)) {
        $userRoles[] = 'director';
    }

    if (in_array('director', $userRoles, true) && ! in_array('operational_manager', $userRoles, true)) {
        $userRoles[] = 'operational_manager';
    }

    $badges = \App\Support\PortalBadges::for($user);
    $groups = config('portal-nav.groups', []);

    $visibleGroups = [];
    foreach ($groups as $group) {
        $visibleItems = [];
        foreach ($group['items'] as $item) {
            if (array_intersect($userRoles, $item['roles'] ?? [])) {
                $visibleItems[] = $item;
            }
        }
        if (! empty($visibleItems)) {
            $visibleGroups[] = array_merge($group, ['items' => $visibleItems]);
        }
    }
@endphp

<aside class="nks-sidebar"
       id="nks-sidebar"
       :class="{ 'is-collapsed': collapsed, 'is-mobile-open': mobileOpen }"
       aria-label="Primary navigation">

    <div class="nks-sidebar-brand">
        <div class="nks-sidebar-logo">
            <span class="nks-logo-mark" aria-hidden="true">NKS</span>
            <span class="nks-brand-name">CAPITAL</span>
        </div>
        <div class="nks-sidebar-app-name">
            Employee Management System
        </div>
    </div>
    

    <nav class="nks-sidebar-nav">
        @foreach ($visibleGroups as $group)
            <div class="nks-nav-group" x-data="{ open: true }" :class="{ 'is-closed': !open }">
                <button type="button"
                        class="nks-nav-group-label"
                        @click="open = !open"
                        :aria-expanded="open.toString()">
                    <span>{{ $group['label'] }}</span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </button>

                <div class="nks-nav-items" x-show="open" x-cloak>
                    @foreach ($group['items'] as $item)
                        @php
                            $active = request()->routeIs($item['route']);
                            $badge = isset($item['badge']) ? ($badges[$item['badge']] ?? 0) : 0;
                        @endphp

                        <a href="{{ route($item['route']) }}"
                           class="nks-nav-item {{ $active ? 'is-active' : '' }}"
                           @if ($active) aria-current="page" @endif
                           wire:navigate>
                            <i class="{{ $item['icon'] }} nks-nav-icon" aria-hidden="true"></i>
                            <span class="nks-nav-label">{{ $item['label'] }}</span>
                            @if ($badge > 0)
                                <span class="nks-nav-badge" aria-label="{{ $badge }} pending">{{ $badge }}</span>
                            @endif
                            <span class="nks-nav-tooltip">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <div class="nks-sidebar-footer">
        <button type="button"
                class="nks-collapse-btn"
                @click="toggleCollapsed()"
                aria-label="Toggle sidebar">
            <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
            <span>Collapse</span>
        </button>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nks-logout-btn">
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                <span>Log out</span>
            </button>
        </form>
    </div>
</aside>