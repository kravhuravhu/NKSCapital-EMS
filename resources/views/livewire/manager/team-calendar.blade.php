<div class="page-team-calendar">
    @if ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($loading)
        <x-nks.spinner block label="Loading team calendar…" />
    @else
        <div class="panel cal-panel">
            <div class="cal-header">
                <i class="fa-solid fa-calendar-days cal-header-icon"></i>
                <div>
                    <h2>Team Leave Calendar</h2>
                    <p>Team members on approved leave this month</p>
                </div>
                <div class="cal-nav">
                    <button type="button"
                            wire:click="previousMonth"
                            class="cal-nav-btn"
                            aria-label="Previous month">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <span class="cal-nav-label">{{ $monthLabel }}</span>
                    <button type="button"
                            wire:click="nextMonth"
                            class="cal-nav-btn"
                            aria-label="Next month">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                    <button type="button"
                            wire:click="goToday"
                            class="nks-btn nks-btn--outline nks-btn--sm cal-today-btn">
                        <i class="fa-solid fa-calendar-day"></i>
                        Today
                    </button>
                    <input type="month"
                           wire:model.live="month"
                           class="cal-month-input">
                </div>
            </div>

            <div class="cal-layout">
                <div class="cal-grid-wrapper">
                    <div class="cal-weekdays">
                        <span>Mon</span>
                        <span>Tue</span>
                        <span>Wed</span>
                        <span>Thu</span>
                        <span>Fri</span>
                        <span>Sat</span>
                        <span>Sun</span>
                    </div>

                    <div class="cal-grid">
                        @foreach ($days as $cell)
                            @if (!$cell['day'])
                                <div class="cal-day cal-day--empty"></div>
                            @else
                                <div class="cal-day {{ $cell['is_weekend'] ? 'is-weekend' : '' }} {{ $cell['is_today'] ? 'is-today' : '' }} {{ $cell['count'] > 0 ? 'has-events' : '' }}"
                                     @if ($cell['count'] > 0) wire:click="selectDay('{{ $cell['date'] }}')" @endif>
                                    <div class="cal-day-number">{{ $cell['day'] }}</div>

                                    @if ($cell['count'] > 0)
                                        <div class="cal-day-events">
                                            @foreach (array_slice($cell['employees'], 0, 3) as $emp)
                                                <span class="cal-event cal-event--{{ \App\Livewire\Manager\TeamCalendar::typeTone($emp['leave_type'] ?? '') }}">
                                                    <i class="{{ \App\Livewire\Manager\TeamCalendar::typeIcon($emp['leave_type'] ?? '') }}"></i>
                                                    {{ \Illuminate\Support\Str::limit($emp['name'] ?? '', 14) }}
                                                </span>
                                            @endforeach

                                            @if ($cell['count'] > 3)
                                                <span class="cal-event-more">+{{ $cell['count'] - 3 }} more</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="cal-sidebar">
                    <div class="cal-summary">
                        <div class="cal-summary-total">
                            <div>
                                <div class="cal-summary-count">{{ $totalLeaves }}</div>
                                <div class="cal-summary-label">Leave days this month</div>
                            </div>
                            <i class="fa-solid fa-umbrella-beach"></i>
                        </div>

                        <ul class="cal-type-list">
                            @foreach ($byType as $type => $count)
                                @continue($count === 0 && $type !== 'annual')
                                <li>
                                    <i class="{{ \App\Livewire\Manager\TeamCalendar::typeIcon($type) }}"></i>
                                    <span class="cal-type-name">{{ ucfirst($type) }}</span>
                                    <strong class="cal-type-count">{{ $count }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="cal-legend">
                        <h3 class="cal-legend-title">Legend</h3>
                        <ul>
                            <li>
                                <i class="fa-solid fa-circle" style="color: var(--nks-success); font-size: 9px;"></i>
                                <span>Annual</span>
                            </li>
                            <li>
                                <i class="fa-solid fa-circle" style="color: #E26B6B; font-size: 9px;"></i>
                                <span>Sick</span>
                            </li>
                            <li>
                                <i class="fa-solid fa-circle" style="color: #5B9BD5; font-size: 9px;"></i>
                                <span>Family</span>
                            </li>
                            <li>
                                <i class="fa-solid fa-circle" style="color: var(--nks-gold); font-size: 9px;"></i>
                                <span>Unpaid</span>
                            </li>
                            <li>
                                <i class="fa-solid fa-circle" style="color: var(--nks-purple); font-size: 9px;"></i>
                                <span>Maternity / Paternity</span>
                            </li>
                        </ul>
                    </div>

                    <div class="cal-note">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Click a day to see everyone on leave.</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showDay && $dayDate)
        <div class="cal-modal-backdrop" wire:click.self="closeDay">
            <div class="cal-modal">
                <header class="cal-modal-head">
                    <h3>Leave on {{ \Carbon\Carbon::parse($dayDate)->format('d F Y') }}</h3>
                    <button type="button" wire:click="closeDay" class="cal-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="cal-modal-body">
                    @foreach ($dayEmployees as $emp)
                        <div class="cal-modal-row">
                            <div class="cal-modal-row-icon cal-event--{{ \App\Livewire\Manager\TeamCalendar::typeTone($emp['leave_type'] ?? '') }}">
                                <i class="{{ \App\Livewire\Manager\TeamCalendar::typeIcon($emp['leave_type'] ?? '') }}"></i>
                            </div>
                            <div class="cal-modal-row-body">
                                <div class="cal-modal-row-name">{{ $emp['name'] ?? '—' }}</div>
                                <div class="cal-modal-row-meta">
                                    {{ $emp['employee_number'] ?? '' }}
                                    @if (!empty($emp['department']))
                                        · {{ $emp['department'] }}
                                    @endif
                                    · {{ ucfirst($emp['leave_type'] ?? '') }}
                                </div>
                            </div>
                            @if (!empty($emp['is_pending_proof']))
                                <span class="cal-modal-flag" title="Awaiting proof upload">
                                    <i class="fa-solid fa-paperclip"></i>
                                </span>
                            @endif
                            @if (!empty($emp['is_unpaid_conversion']))
                                <span class="cal-modal-flag cal-modal-flag--unpaid" title="Converted to unpaid">
                                    <i class="fa-solid fa-coins"></i>
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <footer class="cal-modal-foot">
                    <button type="button" wire:click="closeDay" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>