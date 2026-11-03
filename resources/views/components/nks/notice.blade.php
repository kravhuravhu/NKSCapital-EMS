@props(['title' => null, 'icon' => 'fa-solid fa-circle-info', 'tone' => 'info'])
@php
    $role = $tone === 'danger' ? 'alert' : 'status';
    $live = $tone === 'danger' ? 'assertive' : 'polite';
@endphp
<aside {{ $attributes->merge(['class' => 'nks-notice']) }}
       role="{{ $role }}"
       aria-live="{{ $live }}"
       aria-atomic="true">
    <i class="{{ $icon }}" aria-hidden="true"></i>
    <div class="nks-notice-body">
        @if ($title)
            <strong class="nks-notice-title">{{ $title }}</strong>
        @endif
        {{ $slot }}
    </div>
</aside>