@props(['targets' => 'load', 'loading' => null])

@if ($loading === true)
    {{-- Server-driven: shows while $loading is true, disappears on re-render --}}
    <div class="nks-loading-overlay is-active" role="status" aria-live="polite">
        <div class="nks-loading-card">
            <span class="nks-btn-spinner"></span>
            <span>Loading...</span>
        </div>
    </div>
@elseif ($targets)
    {{-- Client-driven: shows only while a matching Livewire action runs --}}
    <div class="nks-loading-overlay"
         wire:loading.class="is-active"
         wire:target="{{ $targets }}"
         role="status"
         aria-live="polite">
        <div class="nks-loading-card">
            <span class="nks-btn-spinner"></span>
            <span>Loading...</span>
        </div>
    </div>
@endif