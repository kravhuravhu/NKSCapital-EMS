<div class="page-payroll-export">
    @if ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($flash)
        <div class="nks-notice" style="background: var(--nks-mint); color: #1F6B45;">
            <i class="fa-solid fa-circle-check" style="color: #1F6B45;"></i>
            <div class="nks-notice-body">
                <strong style="color: #1F6B45; display: block;">{{ $flash }}</strong>
                @if ($syncResult)
                    <span style="font-size: 11px; opacity: 0.85;">
                        {{ $syncResult['records_count'] ?? 0 }} records
                        · R {{ number_format((float) ($syncResult['total_amount'] ?? 0), 2) }}
                        · Status: {{ ucfirst($syncResult['status'] ?? 'pending') }}
                        @if (!empty($syncResult['external_reference']))
                            · Ref: {{ $syncResult['external_reference'] }}
                        @endif
                    </span>
                @endif
            </div>
        </div>
    @endif

    @if ($lastDownload)
        <div class="nks-notice" style="background: var(--nks-gold-tint); color: #7A6023;">
            <i class="fa-solid fa-file-arrow-down" style="color: #7A6023;"></i>
            <div class="nks-notice-body">
                <strong style="color: #7A6023; display: block;">File ready</strong>
                <span style="font-size: 11px;">
                    {{ $lastDownload['filename'] ?? 'payroll-export' }}
                    · {{ $lastDownload['summary']['total_employees'] ?? 0 }} employees
                </span>
                <a href="{{ $lastDownload['download_url'] ?? '#' }}"
                   download
                   class="px-download-link">
                    <i class="fa-solid fa-download"></i>
                    Download
                </a>
            </div>
        </div>
    @endif

    <div class="px-header-strip">
        <div class="px-header-icon">
            <i class="fa-solid fa-file-export"></i>
        </div>
        <div class="px-header-text">
            <h2>Payroll Export</h2>
            <p>Approved timesheets ready for the payroll system</p>
        </div>
        <div class="px-header-month">
            <label class="px-month-label">Month</label>
            <input type="month" wire:model.live="month" class="px-month-input">
        </div>
    </div>

    @if ($loading)
        <x-nks.spinner block label="Loading payroll data…" />
    @elseif (empty($preview))
        <div class="panel">
            <div class="px-empty">
                <i class="fa-solid fa-clipboard-check"></i>
                <strong>No timesheets ready for payroll</strong>
                <span>
                    Approved timesheets that have not yet been exported will appear here.
                    Try a different month, or approve pending timesheets first.
                </span>
            </div>
        </div>
    @else
        <div class="px-stats">
            <div class="px-stat">
                <div class="px-stat-icon"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="px-stat-label">Employees</div>
                    <div class="px-stat-value">{{ $summary['total_employees'] ?? 0 }}</div>
                </div>
            </div>
            <div class="px-stat">
                <div class="px-stat-icon"><i class="fa-solid fa-clock"></i></div>
                <div>
                    <div class="px-stat-label">Regular Hours</div>
                    <div class="px-stat-value">
                        {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($summary['total_regular_hours'] ?? 0)) }}
                    </div>
                </div>
            </div>
            <div class="px-stat px-stat--purple">
                <div class="px-stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                <div>
                    <div class="px-stat-label">Overtime</div>
                    <div class="px-stat-value">
                        {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($summary['total_overtime_hours'] ?? 0)) }}
                    </div>
                </div>
            </div>
            <div class="px-stat px-stat--gold">
                <div class="px-stat-icon"><i class="fa-solid fa-coins"></i></div>
                <div>
                    <div class="px-stat-label">Total Amount</div>
                    <div class="px-stat-value">
                        R {{ number_format((float) ($summary['total_amount'] ?? 0), 2) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="px-actions">
            <button type="button"
                    wire:click="download('csv')"
                    wire:loading.attr="disabled"
                    wire:target="download"
                    class="nks-btn nks-btn--outline">
                <i class="fa-solid fa-file-csv"></i>
                Download CSV
            </button>
            <button type="button"
                    wire:click="download('pdf')"
                    wire:loading.attr="disabled"
                    wire:target="download"
                    class="nks-btn nks-btn--outline">
                <i class="fa-solid fa-file-pdf"></i>
                Download PDF
            </button>
            <button type="button"
                    wire:click="openSync"
                    class="nks-btn nks-btn--primary">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                Sync to Payroll System
            </button>
        </div>

        <div class="panel px-panel">
            <div class="px-panel-head">
                <h2>Payroll Preview</h2>
                <span class="px-panel-sub">
                    Generated {{ !empty($summary['generated_at']) ? \Carbon\Carbon::parse($summary['generated_at'])->format('d M Y H:i') : '—' }}
                    by {{ $summary['generated_by'] ?? 'System' }}
                </span>
            </div>

            <div class="px-table-wrapper">
                <table class="px-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Month</th>
                            <th style="text-align: right;">Regular</th>
                            <th style="text-align: right;">Overtime</th>
                            <th style="text-align: right;">Rate</th>
                            <th style="text-align: right;">Total</th>
                            <th>Approved</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview as $row)
                            <tr wire:key="px-{{ $row['employee_number'] ?? loop.index }}">
                                <td>
                                    <div class="px-emp-name">{{ $row['employee_name'] ?? '—' }}</div>
                                    <div class="px-emp-number">{{ $row['employee_number'] ?? '' }}</div>
                                </td>
                                <td>{{ $row['department'] ?? '—' }}</td>
                                <td>{{ $row['position'] ?? '—' }}</td>
                                <td>{{ $row['month'] ?? '—' }}</td>
                                <td style="text-align: right;">
                                    {{ number_format((float) ($row['regular_hours'] ?? 0), 2) }}
                                </td>
                                <td style="text-align: right;">
                                    {{ number_format((float) ($row['overtime_hours'] ?? 0), 2) }}
                                </td>
                                <td style="text-align: right;">
                                    R {{ number_format((float) ($row['hourly_rate'] ?? 0), 2) }}
                                </td>
                                <td style="text-align: right;" class="px-emp-total">
                                    R {{ number_format((float) ($row['total_amount'] ?? 0), 2) }}
                                </td>
                                <td>
                                    @if (!empty($row['approved_date']))
                                        <div class="px-approved">
                                            {{ \Carbon\Carbon::parse($row['approved_date'])->format('d M Y') }}
                                        </div>
                                        <div class="px-approved-time">
                                            {{ \Carbon\Carbon::parse($row['approved_date'])->format('H:i') }}
                                        </div>
                                    @else
                                        <span class="px-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="px-tfoot-label">Total</td>
                            <td style="text-align: right;">{{ number_format((float) ($summary['total_regular_hours'] ?? 0), 2) }}</td>
                            <td style="text-align: right;">{{ number_format((float) ($summary['total_overtime_hours'] ?? 0), 2) }}</td>
                            <td></td>
                            <td style="text-align: right;" class="px-tfoot-amount">
                                R {{ number_format((float) ($summary['total_amount'] ?? 0), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="px-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>Sync is irreversible.</strong>
                Once synced, these timesheets are marked as <code>exported_to_payroll</code> and will not appear in future previews for this month. Download a CSV or PDF copy before syncing if you need a permanent record.
            </div>
        </div>
    @endif

    @if ($showSync)
        <div class="px-modal-backdrop" wire:click.self="closeSync">
            <div class="px-modal">
                <header class="px-modal-head">
                    <h3>Confirm Payroll Sync</h3>
                    <button type="button" wire:click="closeSync" class="px-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="px-modal-body">
                    <p class="px-sync-text">
                        You are about to sync <strong>{{ $summary['total_employees'] ?? 0 }} employees</strong>
                        totalling <strong>R {{ number_format((float) ($summary['total_amount'] ?? 0), 2) }}</strong>
                        to the payroll system for {{ !empty($month) ? \Carbon\Carbon::parse($month . '-01')->format('F Y') : 'this period' }}.
                    </p>
                    <div class="px-sync-warning">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>These timesheets will be marked as exported and cannot be re-synced.</span>
                    </div>
                </div>
                <footer class="px-modal-foot">
                    <button type="button" wire:click="closeSync" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="submitSync"
                            wire:loading.attr="disabled"
                            wire:target="submitSync"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        Confirm Sync
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>