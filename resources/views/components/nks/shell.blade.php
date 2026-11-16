@props(['title' => null, 'breadcrumb' => null])

@php $user = auth()->user(); @endphp

<div class="nks-app {{ session()->has('impersonated_by_user_id') ? 'is-impersonating' : '' }}"
     x-data="nksShell()"
     x-init="init()">

    @include('components.nks.impersonation-banner')

    <x-nks.sidebar :user="$user" />

    <div class="nks-main">
        <x-nks.topbar :user="$user" :title="$title" :breadcrumb="$breadcrumb" />

        <main id="main-content" class="nks-content" role="main">
            {{ $slot }}
        </main>
    </div>

    <div class="nks-mobile-backdrop"
         :class="{ 'is-open': mobileOpen }"
         x-show="mobileOpen"
         x-cloak
         @click="mobileOpen = false"
         aria-hidden="true"></div>
</div>