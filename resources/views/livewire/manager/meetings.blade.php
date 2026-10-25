<div class="page-meetings">
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

    @if ($loading)
        <x-nks.spinner block label="Loading meetings…" />
    @else
        <div class="panel mt-panel">
            <div class="mt-header">
                <i class="fa-solid fa-chalkboard-user mt-header-icon"></i>
                <div>
                    <h2>Meetings</h2>
                    <p>Schedule, review, and manage meetings for your team</p>
                </div>
                <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary mt-create-btn">
                    <i class="fa-solid fa-plus"></i>
                    Schedule Meeting
                </button>
            </div>

            <div class="mt-toolbar">
                <nav class="nks-tabs mt-tabs">
                    @foreach (['upcoming' => 'Upcoming', 'today' => 'Today', 'past' => 'Past', 'all' => 'All'] as $key => $label)
                        <button type="button"
                                wire:click="selectScope('{{ $key }}')"
                                class="nks-tab {{ $scope === $key ? 'is-active' : '' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>

                <div class="mt-filters">
                    <select wire:model.live="statusFilter" class="mt-select">
                        <option value="">All statuses</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="postponed">Postponed</option>
                    </select>

                    <select wire:model.live="typeFilter" class="mt-select">
                        <option value="">All types</option>
                        <option value="general">General</option>
                        <option value="team">Team</option>
                        <option value="client">Client</option>
                        <option value="training">Training</option>
                        <option value="review">Review</option>
                    </select>
                </div>
            </div>

            @if (count($meetings) === 0)
                <div class="mt-empty">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <strong>No meetings</strong>
                    <span>
                        @if ($scope === 'upcoming')
                            No upcoming meetings scheduled.
                        @elseif ($scope === 'today')
                            Nothing scheduled for today.
                        @elseif ($scope === 'past')
                            No past meetings found.
                        @else
                            Click <strong>Schedule Meeting</strong> to create your first meeting.
                        @endif
                    </span>
                </div>
            @else
                <div class="mt-list">
                    @foreach ($meetings as $meeting)
                        @php
                            $status = $meeting['status'] ?? 'scheduled';
                            $canEdit = in_array($status, ['scheduled', 'postponed'], true);
                        @endphp
                        <article class="mt-card" wire:key="mt-{{ $meeting['id'] }}">
                            <div class="mt-card-icon mt-card-icon--{{ $status }}">
                                <i class="{{ \App\Livewire\Manager\Meetings::typeIcon($meeting['type'] ?? '') }}"></i>
                            </div>
                            <div class="mt-card-body">
                                <header class="mt-card-head">
                                    <h3>{{ $meeting['title'] ?? '—' }}</h3>
                                    <div class="mt-card-badges">
                                        @if (!empty($meeting['is_mandatory']))
                                            <x-nks.badge tone="gold">Mandatory</x-nks.badge>
                                        @endif
                                        <x-nks.badge :tone="\App\Livewire\Manager\Meetings::statusTone($status)">
                                            {{ ucfirst($status) }}
                                        </x-nks.badge>
                                        @if (!empty($meeting['has_checked_in']))
                                            <x-nks.badge tone="success">Checked In</x-nks.badge>
                                        @endif
                                    </div>
                                </header>
                                <p class="mt-card-meta">
                                    <i class="fa-solid fa-clock"></i>
                                    {{ \Carbon\Carbon::parse($meeting['start_time'])->format('d M Y H:i') }}
                                    &ndash;
                                    {{ \Carbon\Carbon::parse($meeting['end_time'])->format('H:i') }}
                                </p>
                                @if (!empty($meeting['location']))
                                    <p class="mt-card-meta">
                                        <i class="fa-solid fa-location-dot"></i>
                                        {{ $meeting['location'] }}
                                    </p>
                                @endif
                                @if (!empty($meeting['meeting_link']))
                                    <p class="mt-card-meta">
                                        <i class="fa-solid fa-link"></i>
                                        <a href="{{ $meeting['meeting_link'] }}" target="_blank" rel="noopener">
                                            {{ $meeting['meeting_link'] }}
                                        </a>
                                    </p>
                                @endif
                            </div>
                            <div class="mt-card-actions">
                                <button type="button"
                                        wire:click="view({{ $meeting['id'] }})"
                                        class="mt-icon-btn"
                                        title="View">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                @if ($canEdit)
                                    <button type="button"
                                            wire:click="openEdit({{ $meeting['id'] }})"
                                            class="mt-icon-btn"
                                            title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button"
                                            wire:click="openCancel({{ $meeting['id'] }})"
                                            class="mt-icon-btn mt-icon-btn--danger"
                                            title="Cancel">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                @endif
                                @if (in_array($status, ['ongoing', 'completed'], true))
                                    <button type="button"
                                            wire:click="openAttendance({{ $meeting['id'] }})"
                                            class="mt-icon-btn"
                                            title="Attendance">
                                        <i class="fa-solid fa-user-check"></i>
                                    </button>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                @if (($pagination['last_page'] ?? 1) > 1)
                    <div class="mt-pagination">
                        <span class="mt-page-info">
                            Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                            ({{ $pagination['total'] }} total)
                        </span>
                    </div>
                @endif
            @endif
        </div>
    @endif

    @if ($showForm)
        <div class="mt-modal-backdrop" wire:click.self="closeForm">
            <div class="mt-modal mt-modal--lg">
                <header class="mt-modal-head">
                    <h3>{{ $editingId ? 'Edit Meeting' : 'Schedule Meeting' }}</h3>
                    <button type="button" wire:click="closeForm" class="mt-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="mt-modal-body">
                    <div class="mt-form-grid">
                        <div class="nks-field mt-form-full">
                            <label class="nks-label">
                                Title <span class="nks-required">*</span>
                            </label>
                            <input type="text"
                                   wire:model="form.title"
                                   class="nks-input"
                                   placeholder="Weekly team sync">
                        </div>

                        <div class="nks-field">
                            <label class="nks-label">Type</label>
                            <select wire:model="form.type" class="nks-select">
                                <option value="general">General</option>
                                <option value="team">Team</option>
                                <option value="client">Client</option>
                                <option value="training">Training</option>
                                <option value="review">Review</option>
                            </select>
                        </div>

                        <div class="nks-field">
                            <label class="nks-label">Department</label>
                            <input type="text"
                                   wire:model="form.department"
                                   class="nks-input"
                                   placeholder="Leave blank for all">
                        </div>

                        <div class="nks-field">
                            <label class="nks-label">
                                Start <span class="nks-required">*</span>
                            </label>
                            <input type="datetime-local"
                                   wire:model="form.start_time"
                                   class="nks-input">
                        </div>

                        <div class="nks-field">
                            <label class="nks-label">
                                End <span class="nks-required">*</span>
                            </label>
                            <input type="datetime-local"
                                   wire:model="form.end_time"
                                   class="nks-input">
                        </div>

                        <div class="nks-field">
                            <label class="nks-label">Late Threshold (min)</label>
                            <input type="number"
                                   wire:model="form.late_threshold_minutes"
                                   min="1" max="60"
                                   class="nks-input">
                        </div>

                        <div class="nks-field">
                            <label class="nks-label">Excessive Late (min)</label>
                            <input type="number"
                                   wire:model="form.excessive_late_threshold_minutes"
                                   min="5" max="180"
                                   class="nks-input">
                        </div>

                        <div class="nks-field">
                            <label class="nks-label">Location</label>
                            <input type="text"
                                   wire:model="form.location"
                                   class="nks-input"
                                   placeholder="Boardroom 1">
                        </div>

                        <div class="nks-field">
                            <label class="nks-label">Meeting Link</label>
                            <input type="url"
                                   wire:model="form.meeting_link"
                                   class="nks-input"
                                   placeholder="https://meet.example.com/…">
                        </div>

                        <div class="nks-field mt-form-full">
                            <label class="nks-label">Description</label>
                            <textarea wire:model="form.description"
                                      class="nks-textarea"
                                      placeholder="Agenda, pre-reads, or notes for attendees."></textarea>
                        </div>

                        <div class="nks-field mt-form-full">
                            <label class="mt-checkbox">
                                <input type="checkbox" wire:model="form.is_mandatory">
                                <span>Mark as mandatory</span>
                            </label>
                        </div>
                    </div>
                </div>
                <footer class="mt-modal-foot">
                    <button type="button" wire:click="closeForm" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="save" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        {{ $editingId ? 'Save Changes' : 'Schedule' }}
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showView && $viewData)
        @php
            $vstatus = $viewData['status'] ?? 'scheduled';
            $acceptingCheckin = in_array($vstatus, ['scheduled', 'ongoing'], true)
                && now()->between(
                    \Carbon\Carbon::parse($viewData['start_time'])->subMinutes(15),
                    \Carbon\Carbon::parse($viewData['end_time'])
                );
        @endphp
        <div class="mt-modal-backdrop" wire:click.self="closeView">
            <div class="mt-modal mt-modal--lg">
                <header class="mt-modal-head">
                    <h3>{{ $viewData['title'] ?? 'Meeting' }}</h3>
                    <button type="button" wire:click="closeView" class="mt-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="mt-modal-body">
                    <div class="mt-view-meta">
                        <div>
                            <span class="mt-view-label">Status</span>
                            <span class="mt-view-value">
                                <x-nks.badge :tone="\App\Livewire\Manager\Meetings::statusTone($vstatus)">
                                    {{ ucfirst($vstatus) }}
                                </x-nks.badge>
                            </span>
                        </div>
                        <div>
                            <span class="mt-view-label">Type</span>
                            <span class="mt-view-value">{{ ucfirst($viewData['type'] ?? '—') }}</span>
                        </div>
                        <div>
                            <span class="mt-view-label">Start</span>
                            <span class="mt-view-value">
                                {{ \Carbon\Carbon::parse($viewData['start_time'])->format('d M Y H:i') }}
                            </span>
                        </div>
                        <div>
                            <span class="mt-view-label">End</span>
                            <span class="mt-view-value">
                                {{ \Carbon\Carbon::parse($viewData['end_time'])->format('d M Y H:i') }}
                            </span>
                        </div>
                    </div>

                    @if (!empty($viewData['description']))
                        <div class="mt-view-section">
                            <span class="mt-view-label">Description</span>
                            <p class="mt-view-text">{{ $viewData['description'] }}</p>
                        </div>
                    @endif

                    @if (!empty($viewData['location']))
                        <div class="mt-view-section">
                            <span class="mt-view-label">Location</span>
                            <p class="mt-view-text">{{ $viewData['location'] }}</p>
                        </div>
                    @endif

                    @if (!empty($viewData['meeting_link']))
                        <div class="mt-view-section">
                            <span class="mt-view-label">Meeting Link</span>
                            <a href="{{ $viewData['meeting_link'] }}" target="_blank" rel="noopener" class="mt-view-link">
                                {{ $viewData['meeting_link'] }}
                            </a>
                        </div>
                    @endif

                    <div class="mt-view-section">
                        <span class="mt-view-label">Check-In</span>
                        @if ($acceptingCheckin)
                            @if (!$qrData)
                                <button type="button"
                                        wire:click="loadQr({{ $viewData['id'] }})"
                                        class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-qrcode"></i>
                                    Show QR Code
                                </button>
                            @elseif (isset($qrData['error']))
                                <p class="mt-view-text mt-view-text--muted">{{ $qrData['error'] }}</p>
                            @else
                                <div class="mt-qr-block">
                                    @if (!empty($qrData['qr_url']))
                                        <img src="{{ $qrData['qr_url'] }}" alt="Check-in QR code" class="mt-qr-image">
                                    @endif
                                    <p class="mt-qr-hint">
                                        Attendees scan this to check in.
                                    </p>
                                </div>
                            @endif
                        @else
                            <p class="mt-view-text mt-view-text--muted">
                                Check-in is closed. It opens 15 minutes before the start time.
                            </p>
                        @endif
                    </div>

                    <div class="mt-view-actions">
                        <button type="button"
                                wire:click="downloadIcal({{ $viewData['id'] }})"
                                class="nks-btn nks-btn--outline nks-btn--sm">
                            <i class="fa-solid fa-download"></i>
                            Download iCal
                        </button>
                        @if (in_array($vstatus, ['ongoing', 'completed'], true))
                            <button type="button"
                                    wire:click="openAttendance({{ $viewData['id'] }})"
                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                <i class="fa-solid fa-user-check"></i>
                                View Attendance
                            </button>
                        @endif
                    </div>
                </div>
                <footer class="mt-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showCancel)
        <div class="mt-modal-backdrop" wire:click.self="closeCancel">
            <div class="mt-modal">
                <header class="mt-modal-head">
                    <h3>Cancel Meeting</h3>
                    <button type="button" wire:click="closeCancel" class="mt-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="mt-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">
                            Reason <span class="nks-required">*</span>
                        </label>
                        <textarea wire:model="cancelReason"
                                  class="nks-textarea"
                                  placeholder="Explain why the meeting is being cancelled."></textarea>
                    </div>
                </div>
                <footer class="mt-modal-foot">
                    <button type="button" wire:click="closeCancel" class="nks-btn nks-btn--outline">
                        Keep Meeting
                    </button>
                    <button type="button" wire:click="submitCancel" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-ban"></i>
                        Confirm Cancellation
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showAttendance && $attendanceData)
        @php
            $att = $attendanceData;
            $stats = $att['stats'] ?? [];
            $records = $att['attendance'] ?? [];
        @endphp
        <div class="mt-modal-backdrop" wire:click.self="closeAttendance">
            <div class="mt-modal mt-modal--lg">
                <header class="mt-modal-head">
                    <h3>Attendance — {{ $att['meeting']['title'] ?? 'Meeting' }}</h3>
                    <button type="button" wire:click="closeAttendance" class="mt-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="mt-modal-body">
                    <div class="mt-att-stats">
                        <div class="mt-att-stat">
                            <span class="mt-att-stat-label">Total</span>
                            <span class="mt-att-stat-value">{{ $stats['total_checked_in'] ?? 0 }}</span>
                        </div>
                        <div class="mt-att-stat mt-att-stat--success">
                            <span class="mt-att-stat-label">On Time</span>
                            <span class="mt-att-stat-value">{{ $stats['on_time'] ?? 0 }}</span>
                        </div>
                        <div class="mt-att-stat mt-att-stat--warning">
                            <span class="mt-att-stat-label">Late</span>
                            <span class="mt-att-stat-value">{{ $stats['late'] ?? 0 }}</span>
                        </div>
                        <div class="mt-att-stat mt-att-stat--danger">
                            <span class="mt-att-stat-label">Excessive</span>
                            <span class="mt-att-stat-value">{{ $stats['excessive_late'] ?? 0 }}</span>
                        </div>
                        <div class="mt-att-stat">
                            <span class="mt-att-stat-label">Excused</span>
                            <span class="mt-att-stat-value">{{ $stats['excused'] ?? 0 }}</span>
                        </div>
                    </div>

                    @if (count($records) === 0)
                        <div class="mt-att-empty">
                            <i class="fa-solid fa-circle-info"></i>
                            No check-ins recorded yet.
                        </div>
                    @else
                        <div class="mt-att-table-wrapper">
                            <table class="mt-att-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Check-In</th>
                                        <th>Minutes Late</th>
                                        <th>Status</th>
                                        <th>Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($records as $rec)
                                        <tr>
                                            <td>
                                                <div class="mt-att-name">
                                                    {{ $rec['user']['first_name'] ?? '' }}
                                                    {{ $rec['user']['last_name'] ?? '' }}
                                                </div>
                                                <div class="mt-att-meta">
                                                    {{ $rec['user']['employee_number'] ?? '' }}
                                                </div>
                                            </td>
                                            <td>
                                                {{ !empty($rec['check_in_time']) ? \Carbon\Carbon::parse($rec['check_in_time'])->format('d M Y H:i') : '—' }}
                                            </td>
                                            <td>{{ (int) ($rec['minutes_late'] ?? 0) }}</td>
                                            <td>
                                                <x-nks.badge :tone="\App\Livewire\Manager\Meetings::lateTone($rec['late_status'] ?? '')">
                                                    {{ ucfirst(str_replace('_', ' ', $rec['late_status'] ?? '')) }}
                                                </x-nks.badge>
                                            </td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $rec['sign_method'] ?? '—')) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <footer class="mt-modal-foot">
                    <button type="button" wire:click="closeAttendance" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>