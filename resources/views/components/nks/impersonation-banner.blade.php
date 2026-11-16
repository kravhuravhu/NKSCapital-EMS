@php
    $isImpersonating = session()->has('impersonated_by_user_id');
    $adminId = session('impersonation_admin_user_id');
    $admin = $adminId ? \App\Models\User::find($adminId) : null;
    $target = $isImpersonating ? auth()->user() : null;
@endphp

@if ($isImpersonating && $admin && $target)
    <div class="nks-impersonation-banner" role="alert" aria-live="polite">
        <i class="fa-solid fa-user-secret" aria-hidden="true"></i>
        <div class="nks-impersonation-banner-body">
            <strong>Impersonating {{ $target->full_name }} ({{ ucfirst(str_replace('_', ' ', $target->role)) }})</strong>
            <span>Admin: {{ $admin->full_name }}. All actions are audited.</span>
        </div>
        <form method="POST" action="{{ route('impersonation.stop') }}">
            @csrf
            <button type="submit" class="nks-impersonation-stop">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                Stop impersonating
            </button>
        </form>
    </div>
@endif