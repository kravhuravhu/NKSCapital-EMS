@if ($errors->any())
    <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A; margin-bottom: 16px;">
        <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
        <div class="nks-notice-body">
            <strong style="color: #8A2A2A;">Please fix the following:</strong>
            <ul style="margin: 6px 0 0 16px; padding: 0;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
