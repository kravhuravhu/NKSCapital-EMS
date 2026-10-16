@props(['variant' => 'primary', 'icon' => null, 'type' => 'button'])

<button type="{{ $type }}" {{ $attributes->merge(['class' => 'nks-btn nks-btn--' . $variant]) }}>
    @if ($icon)
        <i class="{{ $icon }}"></i>
    @endif
    {{ $slot }}
</button>