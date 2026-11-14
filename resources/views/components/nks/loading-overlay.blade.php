@props(['targets' => 'load'])

<div class="nks-loading-overlay"
     wire:loading.delay.150ms
     wire:target="{{ $targets }}">
    <div class="nks-loading-card">
        <span class="nks-btn-spinner"></span>
        <span>Loading...</span>
    </div>
</div>