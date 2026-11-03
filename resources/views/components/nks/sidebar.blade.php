@props(['user'])

@php
    $links  = config('portal-nav.' . $user->role, []);
    $badges = \App\Support\PortalBadges::for($user);
@endphp
<aside class="sidebar" x-bind:class="sidebarOpen ? 'is-open' : ''">
    <button type="button"
            class="nks-sidebar-close"
            @click="sidebarOpen = false"
            aria-label="Close navigation">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <nav>
        @foreach ($links as $link)
            @php
                $active = request()->routeIs($link['route']);
                $badge  = isset($link['badge']) ? ($badges[$link['badge']] ?? 0) : 0;
            @endphp

            <a href="{{ route($link['route']) }}"
               class="nav-item {{ $active ? 'is-active' : '' }}"
               wire:navigate>
                <i class="{{ $link['icon'] }}"></i>
                <span>{{ $link['label'] }}</span>
                @if ($badge > 0)
                    <span class="nav-badge">{{ $badge }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('logout') }}" class="logout">
        @csrf
        <button type="submit" class="nks-button nks-button-quiet">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Log Out</span>
        </button>
    </form>
</aside>