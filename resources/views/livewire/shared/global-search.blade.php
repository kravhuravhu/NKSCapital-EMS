<div class="nks-search" x-data="{ open: @entangle('open') }" @click.outside="$wire.close()">
    <div class="nks-search-input-wrap">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="text"
               wire:model.live.debounce.300ms="query"
               class="nks-search-input"
               placeholder="Search people, projects, documents..."
               autocomplete="off">
        @if ($searching)
            <i class="fa-solid fa-circle-notch fa-spin nks-search-spinner" aria-hidden="true"></i>
        @endif
        <kbd class="nks-search-kbd">/</kbd>
    </div>

    @if ($open && count($groups) > 0)
        <div class="nks-search-results">
            @foreach ($groups as $group)
                <div class="nks-search-group">
                    <div class="nks-search-group-label">{{ $group['label'] }}</div>
                    @foreach ($group['items'] as $item)
                        <a href="{{ $item['url'] }}" class="nks-search-item" wire:navigate wire:click="close">
                            <span>{{ $item['label'] }}</span>
                            <small>{{ $item['subtitle'] }}</small>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    @elseif ($open && strlen(trim($query)) >= 2)
        <div class="nks-search-results">
            <div class="nks-search-empty">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <p>No results for "{{ $query }}"</p>
            </div>
        </div>
    @endif
</div>