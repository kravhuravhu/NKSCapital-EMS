@props(['tone' => 'muted'])

<span {{ $attributes->merge(['class' => 'nks-badge nks-badge--' . $tone]) }}>
    {{ $slot }}
</span>