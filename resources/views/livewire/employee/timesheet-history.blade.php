<div class="page-timesheet-history">

    @if ($error)
        <div class="tsh-flash tsh-flash--error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    @if ($loading)
        <x-nks.spinner block label="Loading timesheet history..." />
    @else
        <div class="tsh-stats">
            <div class="tsh-stat">
                <div class="tsh-stat-icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></div>
                <div class="tsh-stat-body">
                    <div class="tsh-stat-label">Total</div>
                    <div class="tsh-stat-value">{{ $stats['total'] }}</div>
                </div>
            </div>
            <div class="tsh-stat tsh-stat--success">
                <div class="tsh-stat-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                <div class="tsh-stat-body">
                    <div class="tsh-stat-label">Approved</div>
                    <div class="tsh-stat-value">{{ $stats['approved'] }}</div>
                </div>
            </div>
            <div class="tsh-stat tsh-stat--warning">
                <div class="tsh-stat-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></div>
                <div class="tsh-stat-body">
                    <div class="tsh-stat-label">Pending</div>
                    <div class="tsh-stat-value">{{ $stats['pending'] }}</div>
                </div>
            </div>
            <div class="tsh-stat tsh-stat--danger">
                <div class="tsh-stat-icon"><i class="fa-solid fa-xmark" aria-hidden="true"></i></div>
                <div class="tsh-stat-body">
                    <div class="tsh-stat-label">Rejected</div>
                    <div class="tsh-stat-value">{{ $stats['rejected'] }}</div>
                </div>
            </div>
            <div class="tsh-stat">
                <div class="tsh-stat-icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></div>
                <div class="tsh-stat-body">
                    <div class="tsh-stat-label">Total hours</div>
                    <div class="tsh-stat-value">{{ \App\Livewire\Employee\Timesheet::formatHours($stats['total_hours']) }}</div>
                </div>
            </div>
        </div>

        <nav class="nks-tabs">
            <button type="button"
                    wire:click="selectStatus('')"
                    class="nks-tab {{ $statusFilter === '' ? 'is-active' : '' }}">
                All
            </button>
            <button type="button"
                    wire:click="selectStatus('approved')"
                    class="nks-tab {{ $statusFilter === 'approved' ? 'is-active' : '' }}">
                Approved
            </button>
            <button type="button"
                    wire:click="selectStatus('pending_l1')"
                    class="nks-tab {{ $statusFilter === 'pending_l1' ? 'is-active' : '' }}">
                Pending L1
            </button>
            <button type="button"
                    wire:click="selectStatus('pending_l2')"
                    class="nks-tab {{ $statusFilter === 'pending_l2' ? 'is-active' : '' }}">
                Pending L2
            </button>
            <button type="button"
                    wire:click="selectStatus('rejected')"
                    class="nks-tab {{ $statusFilter === 'rejected' ? 'is-active' : '' }}">
                Rejected
            </button>
            <button type="button"
                    wire:click="selectStatus('draft')"
                    class="nks-tab {{ $statusFilter === 'draft' ? 'is-active' : '' }}">
                Draft
            </button>
        </nav>

        <div class="panel">
            <div class="tsh-panel-head">
                <div>
                    <h2>All timesheets</h2>
                    <p>Click a row to inspect the entries and approval chain</p>
                </div>
                <a href="{{ route('portal.employee.timesheet') }}" wire:navigate class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-plus"></i>
                    Open current month
                </a>
            </div>

            @if (count($items) === 0)
                <div class="nks-card-empty">
                    <i class="fa-solid fa-inbox"></i>
                    <p>
                        @if ($statusFilter !== '')
                            No {{ $statusLabel($statusFilter) }} timesheets found.
                        @else
                            You have not submitted any timesheets yet.
                        @endif
                    </p>
                </div>
            @else
                <div class="nks-table-wrapper">
                    <table class="nks-table tsh-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Status</th>
                                <th>Regular</th>
                                <th>Overtime</th>
                                <th>Total</th>
                                <th>Submitted</th>
                                <th>Approved</th>
                                <th style="width: 80px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $row)
                                @php
                                    $status = $row['status'] ?? 'draft';
                                    $monthKey = isset($row['month_year'])
                                        ? \Carbon\Carbon::parse($row['month_year'])
                                        : null;
                                    $regular = (float) ($row['total_regular_hours'] ?? $row['total_hours'] ?? 0);
                                    $overtime = (float) ($row['total_overtime_hours'] ?? 0);
                                    $approvedAt = $row['level2_approved_at']
                                        ?? $row['client_signature_date']
                                        ?? null;
                                @endphp
                                <tr wire:key="tsh-{{ $row['id'] }}" wire:click="view({{ $row['id'] }})" class="tsh-row">
                                    <td>
                                        <strong>{{ $monthKey ? $monthKey->format('F Y') : '—' }}</strong>
                                    </td>
                                    <td>
                                        <x-nks.badge :tone="$this->statusTone($status)">
                                            {{ $this->statusLabel($status) }}
                                        </x-nks.badge>
                                    </td>
                                    <td>{{ \App\Livewire\Employee\Timesheet::formatHours($regular) }}</td>
                                    <td>{{ \App\Livewire\Employee\Timesheet::formatHours($overtime) }}</td>
                                    <td><strong>{{ \App\Livewire\Employee\Timesheet::formatHours($regular + $overtime) }}</strong></td>
                                    <td>
                                        {{ ! empty($row['created_at']) ? \Carbon\Carbon::parse($row['created_at'])->format('d M Y') : '—' }}
                                    </td>
                                    <td>
                                        {{ $approvedAt ? \Carbon\Carbon::parse($approvedAt)->format('d M Y') : '—' }}
                                    </td>
                                    <td>
                                        <button type="button" wire:click.stop="view({{ $row['id'] }})" class="tsh-open-btn">
                                            <i class="fa-solid fa-chevron-right"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (($pagination['last_page'] ?? 1) > 1)
                    <div class="tsh-pagination">
                        <button type="button"
                                wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                                class="nks-btn nks-btn--outline nks-btn--sm"
                                @if ($pagination['current_page'] <= 1) disabled @endif>
                            <i class="fa-solid fa-chevron-left"></i>
                            Previous
                        </button>
                        <span class="tsh-page-info">
                            Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                            &middot; {{ $pagination['total'] }} total
                        </span>
                        <button type="button"
                                wire:click="gotoPage({{ min($pagination['last_page'], $pagination['current_page'] + 1) }})"
                                class="nks-btn nks-btn--outline nks-btn--sm"
                                @if ($pagination['current_page'] >= $pagination['last_page']) disabled @endif>
                            Next
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                @endif
            @endif
        </div>
    @endif

    @if ($showDetail && $detailData)
        @php
            $ts = $detailData['timesheet'] ?? [];
            $entries = $ts['details'] ?? [];
            $status = $ts['status'] ?? 'draft';
        @endphp
        <div class="nks-modal-backdrop" wire:click.self="closeDetail" @keydown.escape.window="$wire.closeDetail()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <div>
                        <h3>{{ isset($ts['month_year']) ? \Carbon\Carbon::parse($ts['month_year'])->format('F Y') : 'Timesheet' }}</h3>
                        <span class="nks-modal-sub">
                            {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($detailData['total_hours'] ?? 0)) }} logged
                        </span>
                    </div>
                    <button type="button" wire:click="closeDetail" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>

                <div class="nks-modal-body">
                    <div class="tsh-detail-meta">
                        <div>
                            <span class="tsh-detail-label">Status</span>
                            <span class="tsh-detail-value">
                                <x-nks.badge :tone="$this->statusTone($status)">
                                    {{ $this->statusLabel($status) }}
                                </x-nks.badge>
                            </span>
                        </div>
                        <div>
                            <span class="tsh-detail-label">Regular hours</span>
                            <span class="tsh-detail-value">{{ number_format((float) ($ts['total_regular_hours'] ?? 0), 2) }}</span>
                        </div>
                        <div>
                            <span class="tsh-detail-label">Overtime hours</span>
                            <span class="tsh-detail-value">{{ number_format((float) ($ts['total_overtime_hours'] ?? 0), 2) }}</span>
                        </div>
                        <div>
                            <span class="tsh-detail-label">Total</span>
                            <span class="tsh-detail-value">
                                {{ number_format((float) ($ts['total_regular_hours'] ?? 0) + (float) ($ts['total_overtime_hours'] ?? 0), 2) }}
                            </span>
                        </div>
                    </div>

                    @if (count($entries) === 0)
                        <div class="nks-card-empty">
                            <i class="fa-solid fa-circle-info"></i>
                            <p>No entries recorded.</p>
                        </div>
                    @else
                        <div class="nks-table-wrapper">
                            <table class="nks-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Project</th>
                                        <th>Description</th>
                                        <th style="text-align: right;">Hours</th>
                                        <th style="text-align: right;">Overtime</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($entries as $entry)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($entry['work_date'])->format('d M Y') }}</td>
                                            <td>{{ \Carbon\Carbon::parse($entry['work_date'])->format('D') }}</td>
                                            <td>{{ $entry['project']['project_code'] ?? $entry['project']['name'] ?? '—' }}</td>
                                            <td>{{ $entry['task_description'] ?? '—' }}</td>
                                            <td style="text-align: right;">{{ number_format((float) $entry['hours_worked'], 2) }}</td>
                                            <td style="text-align: right;">
                                                {{ $entry['is_overtime'] ? number_format((float) $entry['hours_worked'], 2) : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($workflow === 'pr')
                        <div class="tsh-approvals">
                            <div class="tsh-approval">
                                <span class="tsh-approval-step">1</span>
                                <div class="tsh-approval-body">
                                    <strong>L1 Project Manager</strong>
                                    <span>
                                        @if (! empty($ts['level1_approver']))
                                            {{ $ts['level1_approver']['first_name'] ?? '' }} {{ $ts['level1_approver']['last_name'] ?? '' }}
                                            @if (! empty($ts['level1_approved_at']))
                                                &middot; {{ \Carbon\Carbon::parse($ts['level1_approved_at'])->format('d M Y H:i') }}
                                            @endif
                                        @else
                                            Pending
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="tsh-approval">
                                <span class="tsh-approval-step">2</span>
                                <div class="tsh-approval-body">
                                    <strong>L2 Operational Manager</strong>
                                    <span>
                                        @if (! empty($ts['level2_approver']))
                                            {{ $ts['level2_approver']['first_name'] ?? '' }} {{ $ts['level2_approver']['last_name'] ?? '' }}
                                            @if (! empty($ts['level2_approved_at']))
                                                &middot; {{ \Carbon\Carbon::parse($ts['level2_approved_at'])->format('d M Y H:i') }}
                                            @endif
                                        @else
                                            Pending
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="tsh-approvals">
                            <div class="tsh-approval">
                                <span class="tsh-approval-step">1</span>
                                <div class="tsh-approval-body">
                                    <strong>Client sign off</strong>
                                    <span>
                                        @if (! empty($ts['client_signature_date']))
                                            Signed {{ \Carbon\Carbon::parse($ts['client_signature_date'])->format('d M Y') }}
                                        @else
                                            Not signed yet
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="tsh-approval">
                                <span class="tsh-approval-step">2</span>
                                <div class="tsh-approval-body">
                                    <strong>Emailed to client</strong>
                                    <span>
                                        @if (! empty($ts['client_emailed_at']))
                                            {{ \Carbon\Carbon::parse($ts['client_emailed_at'])->format('d M Y H:i') }}
                                        @else
                                            Not sent yet
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (! empty($ts['level1_rejection_reason']) || ! empty($ts['level2_rejection_reason']))
                        <div class="tsh-rejection">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <div>
                                <strong>Rejection</strong>
                                <p>{{ $ts['level2_rejection_reason'] ?? $ts['level1_rejection_reason'] }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="tsh-detail-actions">
                        @if (! empty($ts['pdf_path']))
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($ts['pdf_path']) }}"
                               target="_blank"
                               class="nks-btn nks-btn--primary nks-btn--sm">
                                <i class="fa-solid fa-file-pdf"></i>
                                Download PDF
                            </a>
                        @endif
                        @if (! empty($ts['signed_pdf_path']))
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($ts['signed_pdf_path']) }}"
                               target="_blank"
                               class="nks-btn nks-btn--outline nks-btn--sm">
                                <i class="fa-solid fa-file-signature"></i>
                                Download signed copy
                            </a>
                        @endif
                    </div>
                </div>

                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeDetail" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                    <a href="{{ route('portal.employee.timesheet') }}" wire:navigate class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-arrow-right"></i>
                        Open timesheet
                    </a>
                </footer>
            </div>
        </div>
    @endif
</div>