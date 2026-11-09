@props(['label' => 'Loading...', 'block' => false])

<div class="nks-spinner {{ $block ? 'nks-spinner--block' : '' }}" role="status" aria-live="polite">
    <i class="fa-solid fa-circle-notch" aria-hidden="true"></i>
    <span>{{ $label }}</span>
</div>