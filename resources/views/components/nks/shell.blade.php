@props(['title' => null, 'breadcrumb' => null])

@php
    $user = auth()->user();
@endphp
<div class="nks-app" x-data="{ sidebarOpen: false }">
    <header class="app-header">
        <button type="button"
                class="nks-hamburger"
                @click="sidebarOpen = true"
                aria-label="Open navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
        <x-nks.brand />

        @if ($title)
            <x-nks.page-heading :title="$title" :breadcrumb="$breadcrumb ?? $title" />
        @endif

        <x-nks.employee :user="$user" />
    </header>

    <div class="app-layout">
        <x-nks.sidebar :user="$user" />

        <main class="page-content">
            {{ $slot }}
        </main>
    </div>
    <div class="nks-sidebar-overlay"
         x-show="sidebarOpen"
         x-cloak
         @click="sidebarOpen = false"
         aria-hidden="true"></div>
</div>