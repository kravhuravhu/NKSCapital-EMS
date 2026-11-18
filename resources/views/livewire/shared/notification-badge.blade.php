<a href="{{ route('portal.employee.notifications') }}"
   class="nks-icon-btn"
   aria-label="Notifications"
   wire:navigate
   wire:poll.60s="refreshCount">
    <i class="fa-regular fa-bell" aria-hidden="true"></i>
    @if ($unread > 0)
        <span class="nks-dot" aria-hidden="true"></span>
    @endif
</a>