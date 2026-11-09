@props(['tone' => 'muted'])

@php
    $normalized = match ($tone) {
        'success', 'green', 'mint' => 'success',
        'gold', 'warning', 'amber' => 'gold',
        'danger', 'error', 'red' => 'red',
        'info', 'blue' => 'blue',
        'purple' => 'purple',
        'muted', 'gray', 'grey', 'neutral' => 'muted',
        default => 'muted',
    };
@endphp

<span {{ $attributes->merge(['class' => 'nks-badge nks-badge--' . $normalized]) }}>
    {{ $slot }}
</span>