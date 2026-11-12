<div class="page-interviews">
    @if ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($flash)
        <div class="nks-notice" style="background: var(--nks-mint); color: #1F6B45;">
            <i class="fa-solid fa-circle-check" style="color: #1F6B45;"></i>
            <div class="nks-notice-body">{{ $flash }}</div>
        </div>
    @endif

    <div class="pl-page-stats">
        <div class="pl-page-stat">
            <div class="pl-page-stat-icon"><i class="fa-solid fa-comments" aria-hidden="true"></i></div>
            <div>
                <div class="pl-page-stat-label">Total</div>
                <div class="pl-page-stat-value">{{ count($interviews) }}</div>
            </div>
        </div>
        <div class="pl-page-stat pl-page-stat--info">
            <div class="pl-page-stat-icon"><i class="fa-solid fa-calendar-day" aria-hidden="true"></i></div>
            <div>
                <div class="pl-page-stat-label">Scheduled</div>
                <div class="pl-page-stat-value">{{ collect($interviews)->whereIn('status', ['scheduled', 'rescheduled'])->count() }}</div>
            </div>
        </div>
        <div class="pl-page-stat pl-page-stat--success">
            <div class="pl-page-stat-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
            <div>
                <div class="pl-page-stat-label">Completed</div>
                <div class="pl-page-stat-value">{{ collect($interviews)->where('status', 'completed')->count() }}</div>
            </div>
        </div>
        <div class="pl-page-stat pl-page-stat--warning">
            <div class="pl-page-stat-icon"><i class="fa-solid fa-pen" aria-hidden="true"></i></div>
            <div>
                <div class="pl-page-stat-label">Feedback pending</div>
                <div class="pl-page-stat-value">{{ collect($interviews)->where('status', 'feedback_pending')->count() }}</div>
            </div>
        </div>
        <div class="pl-page-stat pl-page-stat--danger">
            <div class="pl-page-stat-icon"><i class="fa-solid fa-xmark" aria-hidden="true"></i></div>
            <div>
                <div class="pl-page-stat-label">Cancelled</div>
                <div class="pl-page-stat-value">{{ collect($interviews)->where('status', 'cancelled')->count() }}</div>
            </div>
        </div>
    </div>

    <div class="panel iv-panel">
        <div class="iv-header">
            <i class="fa-solid fa-comments iv-header-icon"></i>
            <div>
                <h2>Interviews</h2>
                <p>Schedule, reschedule, cancel, and record interview feedback</p>
            </div>
            <button type="button" wire:click="openSchedule" class="nks-btn nks-btn--primary iv-create-btn">
                <i class="fa-solid fa-plus"></i>
                Schedule Interview
            </button>
        </div>

        <div class="iv-toolbar">
            <nav class="nks-tabs iv-tabs">
                <button type="button"
                        wire:click="selectView('list')"
                        class="nks-tab {{ $view === 'list' ? 'is-active' : '' }}">
                    <i class="fa-solid fa-list"></i> List
                </button>
                <button type="button"
                        wire:click="selectView('calendar')"
                        class="nks-tab {{ $view === 'calendar' ? 'is-active' : '' }}">
                    <i class="fa-solid fa-calendar-days"></i> Calendar
                </button>
            </nav>

            @if ($view === 'calendar')
                <div class="iv-calendar-nav">
                    <button type="button" wire:click="previousMonth" class="iv-cal-nav-btn" aria-label="Previous month">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <span class="iv-cal-nav-label">
                        {{ \Carbon\Carbon::parse($calendarMonth . '-01')->format('F Y') }}
                    </span>
                    <button type="button" wire:click="nextMonth" class="iv-cal-nav-btn" aria-label="Next month">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                    <button type="button" wire:click="goToday" class="nks-btn nks-btn--outline nks-btn--sm">
                        Today
                    </button>
                </div>
            @endif
        </div>

        <div class="iv-filters">
            <div class="nks-field">
                <label class="nks-label">Status</label>
                <select wire:model="statusFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="rescheduled">Rescheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="no_show">No Show</option>
                </select>
            </div>
            <div class="nks-field">
                <label class="nks-label">Interviewer</label>
                <select wire:model="interviewerFilter" class="nks-select">
                    <option value="">Any</option>
                    @foreach ($interviewers as $u)
                        <option value="{{ $u['id'] }}">{{ $u['first_name'] }} {{ $u['last_name'] }}</option>
                    @endforeach
                </select>
            </div>
            @if ($view === 'list')
                <div class="nks-field">
                    <label class="nks-label">From</label>
                    <input type="date" wire:model="fromFilter" class="nks-input">
                </div>
                <div class="nks-field">
                    <label class="nks-label">To</label>
                    <input type="date" wire:model="toFilter" class="nks-input">
                </div>
            @endif
            <div class="iv-filter-actions">
                <button type="button" wire:click="resetFilters" class="nks-btn nks-btn--ghost nks-btn--sm">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </button>
                <button type="button" wire:click="applyFilters" class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-filter"></i> Apply
                </button>
            </div>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading interviews…" />
        @elseif ($view === 'list')
            @if (count($interviews) === 0)
                <div class="iv-empty">
                    <i class="fa-solid fa-comments"></i>
                    <strong>No interviews found</strong>
                    <span>Adjust filters, or schedule a new interview.</span>
                </div>
            @else
                <div class="iv-table-wrapper">
                    <table class="iv-table">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Interviewer</th>
                                <th>When</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th style="width: 260px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($interviews as $iv)
                                <tr wire:key="iv-{{ $iv['id'] }}">
                                    <td>
                                        <div class="iv-name">
                                            {{ $iv['candidate']['first_name'] ?? '' }} {{ $iv['candidate']['last_name'] ?? '' }}
                                        </div>
                                        <div class="iv-meta">{{ $iv['candidate']['email'] ?? '' }}</div>
                                    </td>
                                    <td>
                                        @if (!empty($iv['interviewer']))
                                            {{ $iv['interviewer']['first_name'] ?? '' }}
                                            {{ $iv['interviewer']['last_name'] ?? '' }}
                                        @else
                                            <span class="iv-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="iv-when">
                                            {{ !empty($iv['scheduled_at']) ? \Carbon\Carbon::parse($iv['scheduled_at'])->format('d M Y') : '—' }}
                                        </div>
                                        <div class="iv-meta">
                                            {{ !empty($iv['scheduled_at']) ? \Carbon\Carbon::parse($iv['scheduled_at'])->format('H:i') : '' }}
                                            · R{{ $iv['round'] ?? 1 }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="iv-type">
                                            <i class="{{ \App\Livewire\Recruiter\Interviews::typeIcon($iv['interview_type'] ?? '') }}"></i>
                                            {{ ucfirst(str_replace('_', ' ', $iv['interview_type'] ?? '—')) }}
                                        </div>
                                    </td>
                                    <td>
                                        <x-nks.badge :tone="\App\Livewire\Recruiter\Interviews::statusTone($iv['status'] ?? '')">
                                            {{ ucfirst(str_replace('_', ' ', $iv['status'] ?? '')) }}
                                        </x-nks.badge>
                                        @if (!empty($iv['feedback_rating']))
                                            <div class="iv-meta">Rated {{ $iv['feedback_rating'] }}/5</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="iv-row-actions">
                                            <button type="button" wire:click="openView({{ $iv['id'] }})"
                                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                                <i class="fa-solid fa-eye"></i> View
                                            </button>
                                            @if ($this->canRescheduleRow($iv))
                                                <button type="button" wire:click="openReschedule({{ $iv['id'] }})"
                                                        class="nks-btn nks-btn--outline nks-btn--sm">
                                                    <i class="fa-solid fa-clock-rotate-left"></i> Move
                                                </button>
                                            @endif
                                            @if ($this->canFeedbackRow($iv))
                                                <button type="button" wire:click="openFeedback({{ $iv['id'] }})"
                                                        class="nks-btn nks-btn--primary nks-btn--sm">
                                                    <i class="fa-solid fa-pen"></i> Feedback
                                                </button>
                                            @endif
                                            @if ($this->canCancelRow($iv))
                                                <button type="button" wire:click="openCancel({{ $iv['id'] }})"
                                                        class="nks-btn nks-btn--danger nks-btn--sm">
                                                    <i class="fa-solid fa-xmark"></i> Cancel
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (($pagination['last_page'] ?? 1) > 1)
                    <div class="iv-pagination">
                        <button type="button" wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                                class="nks-btn nks-btn--outline nks-btn--sm"
                                @if ($pagination['current_page'] <= 1) disabled @endif>
                            <i class="fa-solid fa-chevron-left"></i> Previous
                        </button>
                        <span class="iv-page-info">
                            Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                            ({{ $pagination['total'] }} total)
                        </span>
                        <button type="button" wire:click="gotoPage({{ min($pagination['last_page'], $pagination['current_page'] + 1) }})"
                                class="nks-btn nks-btn--outline nks-btn--sm"
                                @if ($pagination['current_page'] >= $pagination['last_page']) disabled @endif>
                            Next <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                @endif
            @endif
        @else
            <div class="iv-cal-summary">
                <span>
                    <strong>{{ $calendarTotal }}</strong>
                    {{ \Illuminate\Support\Str::plural('interview', $calendarTotal) }}
                    in {{ \Carbon\Carbon::parse($calendarMonth . '-01')->format('F Y') }}
                </span>
            </div>

            <div class="iv-cal-grid-wrapper">
                <div class="iv-cal-weekdays">
                    <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                </div>

                <div class="iv-cal-grid">
                    @foreach ($calendarDays as $cell)
                        @if (!$cell['day'])
                            <div class="iv-cal-day iv-cal-day--empty"></div>
                        @else
                            <div class="iv-cal-day {{ $cell['is_weekend'] ? 'is-weekend' : '' }} {{ $cell['is_today'] ? 'is-today' : '' }} {{ $cell['count'] > 0 ? 'has-events' : '' }}">
                                <div class="iv-cal-day-number">{{ $cell['day'] }}</div>

                                @if ($cell['count'] > 0)
                                    <div class="iv-cal-events">
                                        @foreach (array_slice($cell['interviews'], 0, 3) as $row)
                                            <div class="iv-cal-event iv-cal-event--{{ \App\Livewire\Recruiter\Interviews::statusTone($row['status'] ?? '') }}">
                                                <span class="iv-cal-event-time">{{ $row['time'] ?? '' }}</span>
                                                <span class="iv-cal-event-name">{{ \Illuminate\Support\Str::limit($row['candidate'] ?? '', 16) }}</span>
                                            </div>
                                        @endforeach
                                        @if ($cell['count'] > 3)
                                            <div class="iv-cal-more">+{{ $cell['count'] - 3 }} more</div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- schedule modal --}}
    @if ($showSchedule)
        <div class="iv-modal-backdrop" wire:click.self="closeSchedule">
            <div class="iv-modal iv-modal--lg">
                <header class="iv-modal-head">
                    <h3>Schedule Interview</h3>
                    <button type="button" wire:click="closeSchedule" class="iv-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="iv-modal-body">
                    <div class="iv-form-grid">
                        <div class="nks-field iv-form-full">
                            <label class="nks-label">Candidate <span class="nks-required">*</span></label>
                            <select wire:model="scheduleForm.candidate_id" class="nks-select">
                                <option value="">Select candidate</option>
                                @foreach ($candidates as $c)
                                    <option value="{{ $c['id'] }}">
                                        {{ $c['first_name'] }} {{ $c['last_name'] }}
                                        @if (!empty($c['requisition']))
                                            — {{ $c['requisition']['requisition_code'] ?? '' }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Interviewer <span class="nks-required">*</span></label>
                            <select wire:model="scheduleForm.interviewer_id" class="nks-select">
                                <option value="">Select interviewer</option>
                                @foreach ($interviewers as $u)
                                    <option value="{{ $u['id'] }}">
                                        {{ $u['first_name'] }} {{ $u['last_name'] }} — {{ ucfirst($u['role']) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Round</label>
                            <input type="number" wire:model="scheduleForm.round" min="1" max="10" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Scheduled At <span class="nks-required">*</span></label>
                            <input type="datetime-local" wire:model="scheduleForm.scheduled_at" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Duration (minutes)</label>
                            <input type="number" wire:model="scheduleForm.duration_minutes" min="15" max="480" step="15" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Type</label>
                            <select wire:model="scheduleForm.interview_type" class="nks-select">
                                <option value="video">Video</option>
                                <option value="phone">Phone</option>
                                <option value="in_person">In Person</option>
                            </select>
                        </div>
                        <div class="nks-field iv-form-full">
                            <label class="nks-label">Location / Link</label>
                            <input type="text" wire:model="scheduleForm.location_or_link" class="nks-input"
                                   placeholder="Meeting room or Google Meet link">
                        </div>
                        <div class="nks-field iv-form-full">
                            <label class="nks-label">Meeting Link</label>
                            <input type="url" wire:model="scheduleForm.meeting_link" class="nks-input"
                                   placeholder="https://meet.example.com/...">
                        </div>
                    </div>
                </div>
                <footer class="iv-modal-foot">
                    <button type="button" wire:click="closeSchedule" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitSchedule" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i> Schedule
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- reschedule modal --}}
    @if ($showReschedule)
        <div class="iv-modal-backdrop" wire:click.self="closeReschedule">
            <div class="iv-modal">
                <header class="iv-modal-head">
                    <h3>Reschedule Interview</h3>
                    <button type="button" wire:click="closeReschedule" class="iv-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="iv-modal-body">
                    <p class="iv-text">
                        Moving the interview with <strong>{{ $rescheduleName }}</strong>.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">New Date &amp; Time <span class="nks-required">*</span></label>
                        <input type="datetime-local" wire:model="rescheduleForm.scheduled_at" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Duration (minutes)</label>
                        <input type="number" wire:model="rescheduleForm.duration_minutes" min="15" max="480" step="15" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Location / Link</label>
                        <input type="text" wire:model="rescheduleForm.location_or_link" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Meeting Link</label>
                        <input type="url" wire:model="rescheduleForm.meeting_link" class="nks-input">
                    </div>
                </div>
                <footer class="iv-modal-foot">
                    <button type="button" wire:click="closeReschedule" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitReschedule" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-clock-rotate-left"></i> Reschedule
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- cancel modal --}}
    @if ($showCancel)
        <div class="iv-modal-backdrop" wire:click.self="closeCancel">
            <div class="iv-modal">
                <header class="iv-modal-head">
                    <h3>Cancel Interview</h3>
                    <button type="button" wire:click="closeCancel" class="iv-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="iv-modal-body">
                    <p class="iv-text">
                        Cancel the interview with <strong>{{ $cancelName }}</strong>?
                        The candidate and interviewer will be notified.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Reason <span class="nks-required">*</span></label>
                        <textarea wire:model="cancelReason" class="nks-textarea"
                                  placeholder="Explain why the interview is being cancelled."></textarea>
                    </div>
                </div>
                <footer class="iv-modal-foot">
                    <button type="button" wire:click="closeCancel" class="nks-btn nks-btn--outline">Keep Interview</button>
                    <button type="button" wire:click="submitCancel" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-xmark"></i> Cancel Interview
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- feedback modal --}}
    @if ($showFeedback)
        <div class="iv-modal-backdrop" wire:click.self="closeFeedback">
            <div class="iv-modal">
                <header class="iv-modal-head">
                    <h3>Interview Feedback</h3>
                    <button type="button" wire:click="closeFeedback" class="iv-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="iv-modal-body">
                    <p class="iv-text">
                        Feedback for <strong>{{ $feedbackName }}</strong>.
                        This becomes the permanent record of the interview.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Rating</label>
                        <div class="iv-rating">
                            @for ($i = 1; $i <= 5; $i++)
                                <button type="button"
                                        wire:click="$set('feedbackForm.rating', {{ $i }})"
                                        class="iv-rating-star {{ ($feedbackForm['rating'] ?? 0) >= $i ? 'is-active' : '' }}">
                                    <i class="fa-solid fa-star"></i>
                                </button>
                            @endfor
                            <span class="iv-rating-value">{{ $feedbackForm['rating'] ?? 0 }}/5</span>
                        </div>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Recommendation <span class="nks-required">*</span></label>
                        <select wire:model="feedbackForm.recommendation" class="nks-select">
                            <option value="strong_yes">Strong Yes</option>
                            <option value="yes">Yes</option>
                            <option value="undecided">Undecided</option>
                            <option value="no">No</option>
                            <option value="strong_no">Strong No</option>
                        </select>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Notes</label>
                        <textarea wire:model="feedbackForm.notes" class="nks-textarea"
                                  placeholder="Strengths, concerns, technical depth, culture fit."></textarea>
                    </div>
                </div>
                <footer class="iv-modal-foot">
                    <button type="button" wire:click="closeFeedback" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitFeedback" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i> Submit Feedback
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- view modal --}}
    @if ($showView && $viewData)
        @php
            $vStatus = $viewData['status'] ?? '';
            $vFeedback = $viewData['feedback_rating'] ?? null;
            $vCanReschedule = $this->canRescheduleRow($viewData);
            $vCanCancel = $this->canCancelRow($viewData);
            $vCanFeedback = $this->canFeedbackRow($viewData);
        @endphp
        <div class="iv-modal-backdrop" wire:click.self="closeView">
            <div class="iv-modal iv-modal--lg">
                <header class="iv-modal-head">
                    <div>
                        <h3>
                            Round {{ $viewData['round'] ?? 1 }} —
                            {{ $viewData['candidate']['first_name'] ?? '' }}
                            {{ $viewData['candidate']['last_name'] ?? '' }}
                        </h3>
                        <span class="iv-modal-sub">
                            {{ ucfirst(str_replace('_', ' ', $viewData['interview_type'] ?? '')) }}
                            · {{ $viewData['duration_minutes'] ?? 60 }} min
                        </span>
                    </div>
                    <button type="button" wire:click="closeView" class="iv-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="iv-modal-body">
                    <div class="iv-detail-badges">
                        <x-nks.badge :tone="\App\Livewire\Recruiter\Interviews::statusTone($vStatus)">
                            {{ ucfirst(str_replace('_', ' ', $vStatus)) }}
                        </x-nks.badge>
                        @if (!empty($viewData['reschedule_count']))
                            <x-nks.badge tone="gold">
                                Rescheduled {{ $viewData['reschedule_count'] }}x
                            </x-nks.badge>
                        @endif
                    </div>

                    <div class="iv-detail-grid">
                        <div>
                            <span class="iv-detail-label">Scheduled</span>
                            <span class="iv-detail-value">
                                {{ !empty($viewData['scheduled_at']) ? \Carbon\Carbon::parse($viewData['scheduled_at'])->format('d M Y H:i') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="iv-detail-label">Interviewer</span>
                            <span class="iv-detail-value">
                                @if (!empty($viewData['interviewer']))
                                    {{ $viewData['interviewer']['first_name'] ?? '' }}
                                    {{ $viewData['interviewer']['last_name'] ?? '' }}
                                @else — @endif
                            </span>
                        </div>
                        @if (!empty($viewData['location_or_link']))
                            <div class="iv-detail-full">
                                <span class="iv-detail-label">Location / Link</span>
                                <span class="iv-detail-value">{{ $viewData['location_or_link'] }}</span>
                            </div>
                        @endif
                        @if (!empty($viewData['meeting_link']))
                            <div class="iv-detail-full">
                                <span class="iv-detail-label">Meeting Link</span>
                                <a href="{{ $viewData['meeting_link'] }}" target="_blank" rel="noopener" class="iv-detail-link">
                                    {{ $viewData['meeting_link'] }}
                                </a>
                            </div>
                        @endif
                    </div>

                    @if (!empty($viewData['cancel_reason']))
                        <div class="iv-detail-section">
                            <span class="iv-detail-label">Cancellation Reason</span>
                            <p class="iv-detail-text iv-detail-text--danger">{{ $viewData['cancel_reason'] }}</p>
                        </div>
                    @endif

                    @if ($vFeedback !== null)
                        <div class="iv-detail-section">
                            <span class="iv-detail-label">Feedback</span>
                            <div class="iv-feedback-card">
                                <div class="iv-feedback-head">
                                    <div class="iv-feedback-rating">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i class="fa-solid fa-star {{ $i <= $vFeedback ? 'is-active' : '' }}"></i>
                                        @endfor
                                        <span class="iv-feedback-rating-value">{{ $vFeedback }}/5</span>
                                    </div>
                                    <x-nks.badge :tone="\App\Livewire\Recruiter\Interviews::recommendationTone($viewData['recommendation'] ?? '')">
                                        {{ ucfirst(str_replace('_', ' ', $viewData['recommendation'] ?? '—')) }}
                                    </x-nks.badge>
                                </div>
                                @if (!empty($viewData['feedback_notes']))
                                    <p class="iv-feedback-notes">{{ $viewData['feedback_notes'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                <footer class="iv-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    @if ($vCanFeedback)
                        <button type="button" wire:click="openFeedback({{ $viewData['id'] }})" class="nks-btn nks-btn--primary">
                            <i class="fa-solid fa-pen"></i> Submit Feedback
                        </button>
                    @endif
                    @if ($vCanReschedule)
                        <button type="button" wire:click="openReschedule({{ $viewData['id'] }})" class="nks-btn nks-btn--outline">
                            <i class="fa-solid fa-clock-rotate-left"></i> Reschedule
                        </button>
                    @endif
                    @if ($vCanCancel)
                        <button type="button" wire:click="openCancel({{ $viewData['id'] }})" class="nks-btn nks-btn--danger">
                            <i class="fa-solid fa-xmark"></i> Cancel
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif
</div>