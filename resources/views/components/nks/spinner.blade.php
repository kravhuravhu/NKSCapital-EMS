@props(['label' => 'Loading…', 'block' => false])

<div class="nks-spinner {{ $block ? 'nks-spinner--block' : '' }}">
    <i class="fa-solid fa-circle-notch"></i>
    <span>{{ $label }}</span>
</div>