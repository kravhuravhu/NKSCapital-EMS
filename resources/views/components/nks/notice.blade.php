@props(['title' => null, 'icon' => 'fa-solid fa-circle-info'])

<aside {{ $attributes->merge(['class' => 'nks-notice']) }}>
    <i class="{{ $icon }}"></i>
    <div class="nks-notice-body">
        @if ($title)
            <strong class="nks-notice-title">{{ $title }}</strong>
        @endif
        {{ $slot }}
    </div>
</aside>