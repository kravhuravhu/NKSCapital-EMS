@props(['error' => null, 'success' => null, 'warning' => null, 'info' => null])

@if ($error || $success || $warning || $info)
    <div class="nks-notice-stack" role="status" aria-live="polite">
        @if ($error)
            <div class="nks-notice nks-notice--error">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <div class="nks-notice-body">{{ $error }}</div>
            </div>
        @endif

        @if ($success)
            <div class="nks-notice nks-notice--success">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <div class="nks-notice-body">{{ $success }}</div>
            </div>
        @endif

        @if ($warning)
            <div class="nks-notice nks-notice--warning">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <div class="nks-notice-body">{{ $warning }}</div>
            </div>
        @endif

        @if ($info)
            <div class="nks-notice nks-notice--info">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <div class="nks-notice-body">{{ $info }}</div>
            </div>
        @endif
    </div>
@endif