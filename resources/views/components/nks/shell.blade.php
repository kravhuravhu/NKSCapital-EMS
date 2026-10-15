@props(['title' => null, 'breadcrumb' => null])

@php
    $user = auth()->user();
@endphp

<div class="nks-app">
    <header class="app-header">
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
</div>