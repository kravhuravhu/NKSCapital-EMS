<div class="page-notifications" wire:init="load" wire:poll.60s="poll">
    <x-nks.loading-overlay :loading="$loading" targets="load" />
    <x-nks.notice-stack :error="$error" :success="$flash" />

    @if ($loading)
        <div class="panel" style="min-height: 400px;"></div>
    @else
        <div class="nf-layout">
            <div class="nf-feed">
                <div class="panel">
                    <div class="page-toolbar">
                        <div>
                            <h2>Notifications</h2>
                            <p>{{ $unreadCount }} unread</p>
                        </div>
                        @if ($unreadCount > 0)
                            <button type="button" wire:click="markAllRead" class="nks-btn nks-btn--outline nks-btn--sm"
                                    wire:loading.attr="disabled" wire:target="markAllRead">
                                <i class="fa-solid fa-check-double" wire:loading.remove wire:target="markAllRead"></i>
                                <span class="nks-btn-spinner" wire:loading wire:target="markAllRead"></span>
                                Mark all as read
                            </button>
                        @endif
                    </div>

                    @if (count($groups) === 0)
                        <div class="nks-card-empty">
                            <i class="fa-regular fa-bell-slash"></i>
                            <p>You have no notifications yet.</p>
                        </div>
                    @else
                        @foreach ($groups as $group)
                            <section class="nf-group">
                                <h3 class="nf-group-title">{{ $group['label'] }}</h3>

                                @foreach ($group['items'] as $item)
                                    <div class="nf-item {{ ($item['is_read'] ?? false) ? 'is-read' : '' }}">
                                        <div class="nf-icon nf-icon--{{ self::toneForType($item['type'] ?? '') }}">
                                            <i class="{{ self::iconForType($item['type'] ?? '') }}" aria-hidden="true"></i>
                                        </div>
                                        <div class="nf-body">
                                            <strong>{{ $item['title'] ?? 'Notification' }}</strong>
                                            <p>{{ $item['message'] ?? '' }}</p>
                                            <span>{{ self::prettyTime($item['created_at'] ?? null) }}</span>
                                        </div>
                                        <div class="nf-actions">
                                            @if (! ($item['is_read'] ?? false))
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

            <div class="nf-prefs">
                <div class="panel">
                    <h2>Preferences</h2>
                    <p class="nks-help" style="margin-bottom: 16px;">
                        Choose what arrives in app, by email, or as a weekly digest.
                    </p>

                    @foreach ($preferences as $pref)
                        @php
                            $category = $pref['category'] ?? 'general';
                            $label = ucwords(str_replace('_', ' ', $category));
                        @endphp
                        <div class="nf-pref-row">
                            <div class="nf-pref-body">
                                <strong>{{ $label }}</strong>
                                <div class="nf-pref-toggles">
                                    <button type="button"
                                            wire:click="togglePreference('{{ $category }}', 'in_app')"
                                            class="nf-pref-toggle {{ ! empty($pref['in_app']) ? 'is-on' : '' }}">
                                        <i class="fa-solid fa-bell" aria-hidden="true"></i>
                                        In app
                                    </button>
                                    <button type="button"
                                            wire:click="togglePreference('{{ $category }}', 'email')"
                                            class="nf-pref-toggle {{ ! empty($pref['email']) ? 'is-on' : '' }}">
                                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                        Email
                                    </button>
                                    <button type="button"
                                            wire:click="togglePreference('{{ $category }}', 'digest_only')"
                                            class="nf-pref-toggle {{ ! empty($pref['digest_only']) ? 'is-on' : '' }}">
                                        <i class="fa-solid fa-newspaper" aria-hidden="true"></i>
                                        Digest
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @if (count($preferences) === 0)
                        <div class="nks-card-empty">
                            <i class="fa-regular fa-bell"></i>
                            <p>No preference categories yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>