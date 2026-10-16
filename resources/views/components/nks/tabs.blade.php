@props(['tabs' => []])

<nav class="nks-tabs" aria-label="Section tabs">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['url'] }}" class="nks-tab {{ ($tab['active'] ?? false) ? 'is-active' : '' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>