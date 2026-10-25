<div class="page-notifications">

    @if ($error)
        <div class="nf-flash nf-flash--error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    @if ($flash)
        <div class="nf-flash nf-flash--success">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ $flash }}</span>
        </div>
    @endif

    <div class="nf-layout">

        {{-- LEFT: feed --}}
        <div class="nf-feed">
            <div class="panel nf-feed-panel">

                <div class="nf-feed-header">
                    <div>
                        <h2>Notifications</h2>
                        <p>{{ $unreadCount }} unread</p>
                    </div>

                    @if ($unreadCount > 0)
                        <button type="button" wire:click="markAllRead" class="nks-btn nks-btn--outline nks-btn--sm">
                            <i class="fa-solid fa-check-double"></i>
                            Mark all as read
                        </button>
                    @endif
                </div>

                @if ($loading)
                    <x-nks.spinner block label="Loading notifications…" />
                @elseif (count($groups) === 0)
                    <div class="nf-empty">
                        <i class="fa-regular fa-bell-slash"></i>
                        <strong>No notifications yet</strong>
                        <span>When something needs your attention, it will appear here.</span>
                    </div>
                @else
                    @foreach ($groups as $group)
                        <section class="nf-group">
                            <h3 class="nf-group-title">{{ $group['label'] }}</h3>

                            @foreach ($group['items'] as $item)
                                <div class="nf-item {{ ($item['is_read'] ?? false) ? 'is-read' : '' }}" wire:key="notif-{{ $item['id'] }}">
                                    <div class="nf-item-icon">
                                        <i class="{{ \App\Livewire\Employee\Notifications::iconForType($item['type'] ?? null) }}"></i>
                                    </div>

                                    <div class="nf-item-body">
                                        <div class="nf-item-title">{{ $item['title'] ?? 'Notification' }}</div>
                                        <div class="nf-item-text">{{ $item['message'] ?? '' }}</div>
                                        <div class="nf-item-time">
                                            {{ \App\Livewire\Employee\Notifications::prettyTime($item['created_at'] ?? null) }}
                                        </div>
                                    </div>

                                    <div class="nf-item-actions">
                                        @if (!($item['is_read'] ?? false))
                                            <x-nks.badge tone="success">New</x-nks.badge>
                                        @endif

                                        @if (!($item['is_read'] ?? false))
                                            <button type="button"
                                                    wire:click="markRead({{ $item['id'] }})"
                                                    class="nf-icon-btn"
                                                    title="Mark as read">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        @endif

                                        <button type="button"
                                                wire:click="deleteNotification({{ $item['id'] }})"
                                                class="nf-icon-btn nf-icon-btn--danger"
                                                title="Delete">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </section>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- RIGHT: preferences --}}
        <div class="nf-prefs">
            <div class="panel nf-prefs-panel">
                <h2>Notification Preferences</h2>
                <p class="nf-prefs-subtitle">Choose what notifications you want to receive</p>

                @foreach ($preferences as $pref)
                    @php
                        $category = $pref['category'] ?? 'general';
                        $enabled  = (bool) ($pref['in_app'] ?? true);
                        $label    = ucwords(str_replace('_', ' ', $category));
                        $desc     = match ($category) {
                            'timesheet'  => 'Approvals, rejections, reminders',
                            'leave'      => 'Approvals, rejections, updates',
                            'asset'      => 'Loans, returns, overdue',
                            'contract'   => 'Renewals, expiry',
                            'recruitment'=> 'Pipeline, offers, interviews',
                            'meeting'    => 'Invitations, attendance',
                            'payroll'    => 'Exports, approvals',
                            'delegation' => 'Approval delegation',
                            default      => 'General system notifications',
                        };
                    @endphp

                    <div class="nf-pref-row">
                        <div class="nf-pref-text">
                            <div class="nf-pref-title">{{ $label }}</div>
                            <div class="nf-pref-desc">{{ $desc }}</div>
                        </div>

                        <button type="button"
                                wire:click="togglePreference('{{ $category }}', 'in_app')"
                                class="nks-toggle {{ $enabled ? 'is-on' : '' }}"
                                role="switch"
                                aria-checked="{{ $enabled ? 'true' : 'false' }}"
                                aria-label="{{ $label }} notifications">
                            <span class="nks-toggle-knob"></span>
                        </button>
                    </div>
                @endforeach

                @if (count($preferences) === 0)
                    <div class="nf-empty" style="padding: 20px 0;">
                        <i class="fa-regular fa-bell"></i>
                        <strong>No preferences loaded</strong>
                        <span>Once the API returns your settings, they will appear here.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>