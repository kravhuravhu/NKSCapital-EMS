@props(['title' => null])

<div {{ $attributes->merge(['class' => 'panel']) }}>
    @if ($title)
        <h2>{{ $title }}</h2>
    @endif
    {{ $slot }}
</div>