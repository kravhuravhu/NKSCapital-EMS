@props(['title', 'subtitle' => null, 'icon' => 'fa-solid fa-circle'])

<header class="section-heading">
    <div class="section-heading-icon">
        <i class="{{ $icon }}"></i>
    </div>
    <div class="section-heading-text">
        <div class="section-heading-title">{{ $title }}</div>
        @if ($subtitle)
            <div class="section-heading-sub">{{ $subtitle }}</div>
        @endif
    </div>
</header>