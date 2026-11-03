@props(['title' => null, 'breadcrumb' => null])

@php
    $user = auth()->user();
@endphp
<div class="nks-app" x-data="{ sidebarOpen: false }">
    <header class="app-header">
        <button type="button"
                class="nks-hamburger"
                @click="sidebarOpen = true"
                aria-label="Open navigation menu"
                aria-expanded="false"
                x-bind:aria-expanded="sidebarOpen ? 'true' : 'false'"
                aria-controls="nks-sidebar">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <x-nks.brand />

        @if ($title)
            <x-nks.page-heading :title="$title" :breadcrumb="$breadcrumb ?? $title" />
        @endif

        <x-nks.employee :user="$user" />
    </header>

    <div class="app-layout">
        <x-nks.sidebar :user="$user" />
        <main id="main-content" class="page-content" role="main" tabindex="-1">
            {{ $slot }}
        </main>
    </div>
    <div class="nks-sidebar-overlay"
         x-show="sidebarOpen"
         x-cloak
         @click="sidebarOpen = false"
         aria-hidden="true"></div>
</div>