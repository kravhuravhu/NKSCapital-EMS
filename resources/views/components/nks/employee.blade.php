@props(['user'])

<div class="employee">
    <div>
        <span>{{ $user->full_name ?? $user->email }}</span>
        <small>{{ $user->position ?? ucfirst($user->role) }}</small>
    </div>
    <div class="employee-avatar">
        <i class="fa-solid fa-user"></i>
    </div>
</div>