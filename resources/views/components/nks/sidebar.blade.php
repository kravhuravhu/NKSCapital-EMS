@props(['user'])

@php
    $links  = config('portal-nav.' . $user->role, []);
    $badges = \App\Support\PortalBadges::for($user);
@endphp
<aside class="sidebar" id="nks-sidebar" x-bind:class="sidebarOpen ? 'is-open' : ''">
    <button type="button"
            class="nks-sidebar-close"
            @click="sidebarOpen = false"
            aria-label="Close navigation menu">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
    <nav aria-label="Primary">
        @foreach ($links as $link)
            @php
                $active = request()->routeIs($link['route']);
                $badge  = isset($link['badge']) ? ($badges[$link['badge']] ?? 0) : 0;
            @endphp

            <a href="{{ route($link['route']) }}"
               class="nav-item {{ $active ? 'is-active' : '' }}"
               @if ($active) aria-current="page" @endif
               wire:navigate>
                <i class="{{ $link['icon'] }}" aria-hidden="true"></i>
                <span class="nav-item-label">{{ $link['label'] }}</span>
                @if ($badge > 0)
                    <span class="nav-badge" aria-label="{{ $badge }} pending">
                        {{ $badge }}
                    </span>
                @endif
            </a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('logout') }}" class="logout">
        @csrf
        <button type="submit" class="nks-button nks-button-quiet">
            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            <span>Log Out</span>
        </button>
    </form>
</aside>