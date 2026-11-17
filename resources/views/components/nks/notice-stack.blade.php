@props([
    'error' => null,
    'success' => null,
    'warning' => null,
    'info' => null,
    'dismissAfter' => 12000,
])

@php
    $hasNotice = $error || $success || $warning || $info;
    $hash = md5(($error ?? '') . '|' . ($success ?? '') . '|' . ($warning ?? '') . '|' . ($info ?? ''));
@endphp

@if ($hasNotice)
    <div class="nks-notice-stack"
         wire:key="notice-{{ $hash }}"
         x-data="{ visible: true }"
         x-init="
             setTimeout(() => {
                 visible = false;
                 setTimeout(() => {
                     if (typeof $wire !== 'undefined' && typeof $wire.clearFlashMessages === 'function') {
                         $wire.clearFlashMessages();
                     }
                 }, 400);
             }, {{ (int) $dismissAfter }});
         "
         x-show="visible"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         role="status"
         aria-live="polite">
        @if ($error)
            <div class="nks-notice nks-notice--error">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <div class="nks-notice-body">{{ $error }}</div>
            </div>
        @endif

        @if ($success)
            <div class="nks-notice nks-notice--success">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <div class="nks-notice-body">{{ $success }}</div>
            </div>
        @endif

        @if ($warning)
            <div class="nks-notice nks-notice--warning">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <div class="nks-notice-body">{{ $warning }}</div>
            </div>
        @endif

        @if ($info)
            <div class="nks-notice nks-notice--info">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <div class="nks-notice-body">{{ $info }}</div>
            </div>
        @endif
    </div>
@endif