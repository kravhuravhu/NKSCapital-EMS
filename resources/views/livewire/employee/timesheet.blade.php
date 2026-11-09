<div class="page-timesheets">

    @if ($error)
        <div class="ts-flash ts-flash--error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    @if ($flash)
        <div class="ts-flash ts-flash--success">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ $flash }}</span>
        </div>
    @endif

    @if ($loading)
        <x-nks.spinner block label="Loading timesheet..." />
    @else
        <div class="panel ts-panel">

            <div class="ts-header">
                <i class="fa-solid fa-clipboard-list ts-header-icon" aria-hidden="true"></i>

                <h2 class="ts-header-title">
                    {{ \Carbon\Carbon::parse($monthYear)->format('F Y') }} Timesheet
                </h2>

                <span class="nks-badge nks-badge--{{ $timesheet ? 'gold' : 'muted' }} ts-header-status">
                    {{ $timesheet ? ucfirst(str_replace('_', ' ', $timesheet['status'])) : 'Draft' }}
                </span>

                <span class="ts-header-deadline">
                    Deadline: {{ \Carbon\Carbon::parse($monthYear)->endOfMonth()->format('d F Y') }}
                </span>

                <input type="month" wire:model.live="monthYear" class="ts-header-month">
            </div>

            @if (count($this->leaveDayGroups) > 0)
                <div class="ts-leave-banner">
                    <div class="ts-leave-banner-icon">
                        <i class="fa-solid fa-umbrella-beach" aria-hidden="true"></i>
                    </div>
                    <div class="ts-leave-banner-body">
                        <strong>You are on leave this month</strong>
                        <div class="ts-leave-banner-chips">
                            @foreach ($this->leaveDayGroups as $group)
                                <span class="ts-leave-chip">
                                    <i class="fa-solid fa-circle" aria-hidden="true"></i>
                                    {{ $group['same_day']
                                        ? $group['start']
                                        : $group['start'] . ' - ' . $group['end'] }}
                                    &middot; {{ ucfirst($group['type']) }}
                                </span>
                            @endforeach
                        </div>
                        <p>These days are excluded from timesheet rows and from the working-days target.</p>
                    </div>
                </div>
            @endif

            <div class="ts-metrics">
                <div class="ts-metric">
                    <div class="ts-metric-label">Regular hours</div>
                    <div class="ts-metric-value">{{ self::formatHours($this->regularHours) }}</div>
                </div>
                <div class="ts-metric">
                    <div class="ts-metric-label">Overtime</div>
                    <div class="ts-metric-value ts-metric-value--overtime">{{ self::formatHours($this->overtimeHours) }}</div>
                </div>
                <div class="ts-metric">
                    <div class="ts-metric-label">Leave days</div>
                    <div class="ts-metric-value ts-metric-value--leave">{{ (int) $this->leaveTaken }} {{ \Illuminate\Support\Str::plural('day', (int) $this->leaveTaken) }}</div>
                </div>
                <div class="ts-metric">
                    <div class="ts-metric-label">Total</div>
                    <div class="ts-metric-value">{{ self::formatHours($this->totalHours) }}</div>
                </div>
                <div class="ts-metric ts-metric--progress">
                    <div class="ts-progress-head">
                        <div class="ts-progress-labels">
                            <span class="ts-metric-label">Progress</span>
                            <span class="ts-metric-sub">
                                {{ $this->workingDaysCompleted }} of {{ $this->workingDaysTarget }} working days
                            </span>
                        </div>
                        <span class="ts-progress-percent">{{ $this->progressPercent }}%</span>
                    </div>
                    <div class="ts-progress-bar">
                        <div class="ts-progress-fill" style="width: {{ $this->progressPercent }}%"></div>
                    </div>
                </div>
            </div>

            <div class="ts-table-wrapper">
                <table class="ts-table">
                    <colgroup>
                        <col style="width: 10%">
                        <col style="width: 5%">
                        <col style="width: 16%">
                        <col style="width: 26%">
                        <col style="width: 10%">
                        <col style="width: 11%">
                        <col style="width: 10%">
                        <col style="width: 12%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Task / Project</th>
                            <th>Task Description</th>
                            <th>Hours</th>
                            <th>Overtime</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $index => $entry)
                            <tr wire:key="entry-{{ $index }}-{{ $entry['work_date'] }}">
                                <td class="ts-td-date">
                                    {{ \Carbon\Carbon::parse($entry['work_date'])->format('d M Y') }}
                                </td>
                                <td class="ts-td-day">
                                    {{ \Carbon\Carbon::parse($entry['work_date'])->format('D') }}
                                </td>
                                <td>
                                    <input type="text"
                                           wire:model="entries.{{ $index }}.project_name"
                                           class="ts-cell-input"
                                           placeholder="Project">
                                </td>
                                <td>
                                    <input type="text"
                                           wire:model="entries.{{ $index }}.task_description"
                                           class="ts-cell-input"
                                           placeholder="What did you work on?">
                                </td>
                                <td>
                                    <input type="text"
                                           wire:model.live.debounce.400ms="entries.{{ $index }}.hours_worked"
                                           class="ts-cell-input ts-cell-input--hours"
                                           placeholder="0.00">
                                </td>
                                <td>
                                    <input type="text"
                                           wire:model.live.debounce.400ms="entries.{{ $index }}.overtime_hours"
                                           class="ts-cell-input ts-cell-input--hours"
                                           placeholder="0.00">
                                </td>
                                <td class="ts-td-total">
                                    {{ self::formatHours((float) $entry['hours_worked'] + (float) $entry['overtime_hours']) }}
                                </td>
                                <td>
                                    <div class="ts-row-actions">
                                        <button type="button"
                                                wire:click="duplicateRow({{ $index }})"
                                                class="ts-row-btn"
                                                title="Duplicate row">
                                            <i class="fa-regular fa-copy"></i>
                                        </button>
                                        <button type="button"
                                                wire:click="deleteRow({{ $index }})"
                                                class="ts-row-btn ts-row-btn--danger"
                                                title="Clear or delete">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="ts-empty">
                                    <i class="fa-solid fa-circle-info"></i>
                                    No entries yet. Click <strong>Add Row</strong> or try <strong>Fill with AI</strong>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="ts-footer">
                <button type="button" wire:click="addRow" class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-plus"></i>
                    Add Row
                </button>

                <button type="button" wire:click="openAiFill" class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    Fill with AI
                </button>

                <span class="ts-footer-spacer"></span>

                <span class="ts-footer-label">Total</span>
                <span class="ts-footer-value">{{ self::formatHours($this->regularHours) }}</span>
                <span class="ts-footer-value">{{ self::formatHours($this->overtimeHours) }}</span>
                <span class="ts-footer-value">{{ self::formatHours($this->totalHours) }}</span>
            </div>

            <div class="ts-bottom">
                <div class="ts-bottom-block">
                    <h3 class="ts-bottom-title">Additional Notes <span>(Optional)</span></h3>
                    <textarea wire:model="notes"
                              class="ts-bottom-textarea"
                              placeholder="Anything the approver should know about this month"></textarea>
                </div>

                <div class="ts-bottom-block">
                    <h3 class="ts-bottom-title">Actions</h3>

                    <div class="ts-actions">
                        <button type="button" wire:click="openHistory" class="nks-btn nks-btn--outline nks-btn--sm">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            View History
                        </button>

                        <button type="button" wire:click="openPreview" class="nks-btn nks-btn--outline nks-btn--sm">
                            <i class="fa-solid fa-eye"></i>
                            Preview
                        </button>

                        @if ($workflow === 'ps')
                            @if (! $timesheet)
                                <button type="button" wire:click="createTimesheet"
                                        class="nks-btn nks-btn--primary nks-btn--sm nks-btn--block"
                                        wire:loading.attr="disabled" wire:target="createTimesheet">
                                    <i class="fa-solid fa-plus"></i>
                                    Create Timesheet
                                </button>
                            @else
                                <button type="button" wire:click="generateTemplate"
                                        class="nks-btn nks-btn--primary nks-btn--sm">
                                    <i class="fa-solid fa-file-lines"></i>
                                    Generate Template
                                </button>

                                <a href="/api/v1/timesheet/ps/download-template?timesheet_id={{ $timesheet['id'] }}"
                                   class="nks-btn nks-btn--primary nks-btn--sm" target="_blank">
                                    <i class="fa-solid fa-download"></i>
                                    Download
                                </a>

                                <div class="ts-upload-row">
                                    <input type="file" wire:model="signedFile" accept="application/pdf" class="ts-file-input">
                                    <button type="button" wire:click="uploadSigned"
                                            class="nks-btn nks-btn--outline nks-btn--sm">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        Upload Signed
                                    </button>
                                </div>

                                <div class="ts-email-row">
                                    <input type="email" wire:model="clientEmail" class="ts-cell-input"
                                           placeholder="Client manager email">
                                    <button type="button" wire:click="sendToClient"
                                            class="nks-btn nks-btn--primary nks-btn--sm">
                                        <i class="fa-solid fa-paper-plane"></i>
                                        Send
                                    </button>
                                </div>
                            @endif
                        @else
                            <button type="button" wire:click="saveDraft"
                                    class="nks-btn nks-btn--outline nks-btn--sm"
                                    wire:loading.attr="disabled" wire:target="saveDraft">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Save Draft
                            </button>

                            <button type="button" wire:click="submitForApproval"
                                    class="nks-btn nks-btn--primary nks-btn--sm nks-btn--block"
                                    wire:loading.attr="disabled" wire:target="submitForApproval">
                                <i class="fa-solid fa-paper-plane"></i>
                                Submit for Approval
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="ts-notice">
                <i class="fa-solid fa-circle-info"></i>
                <span>
                    @if ($workflow === 'ps')
                        Your timesheet must be signed by the client. Once uploaded, it is emailed to the client manager and stored for the Operational Manager.
                    @else
                        Your timesheet goes through two approvals, first the Project Manager then the Operational Manager. Payroll receives the final approved copy automatically.
                    @endif
                </span>
            </div>
        </div>
    @endif

    {{-- History --}}
    @if ($showHistory)
        <div class="nks-modal-backdrop" wire:click.self="closeHistory" @keydown.escape.window="$wire.closeHistory()">
            <div class="nks-modal nks-modal--md" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Timesheet History</h3>
                    <button type="button" wire:click="closeHistory" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    @if (count($historyItems) === 0)
                        <div class="ts-empty" style="padding: 40px 16px;">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            No previous timesheets yet.
                        </div>
                    @else
                        <div class="ts-history-list">
                            @foreach ($historyItems as $row)
                                @php
                                    $monthKey = isset($row['month_year'])
                                        ? \Carbon\Carbon::parse($row['month_year'])->format('Y-m-d')
                                        : null;
                                    $isCurrent = $monthKey === \Carbon\Carbon::parse($monthYear)->startOfMonth()->format('Y-m-d');
                                    $total = (float) ($row['total_regular_hours'] ?? 0) + (float) ($row['total_overtime_hours'] ?? 0);
                                @endphp
                                <div class="ts-history-item {{ $isCurrent ? 'is-current' : '' }}">
                                    <div class="ts-history-body">
                                        <strong>{{ $monthKey ? \Carbon\Carbon::parse($monthKey)->format('F Y') : '—' }}</strong>
                                        <span>
                                            {{ self::formatHours($total) }} logged
                                            &middot; {{ ucfirst(str_replace('_', ' ', $row['status'] ?? 'draft')) }}
                                        </span>
                                    </div>
                                    @if ($monthKey && ! $isCurrent)
                                        <button type="button"
                                                wire:click="loadHistoryMonth('{{ $monthKey }}')"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            View
                                        </button>
                                    @else
                                        <span class="nks-badge nks-badge--gold">Current</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeHistory" class="nks-btn nks-btn--outline">Close</button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Preview --}}
    @if ($showPreview)
        <div class="nks-modal-backdrop" wire:click.self="closePreview" @keydown.escape.window="$wire.closePreview()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <div>
                        <h3>Timesheet Preview</h3>
                        <span class="nks-modal-sub">
                            {{ \Carbon\Carbon::parse($monthYear)->format('F Y') }}
                            &middot; {{ auth()->user()->full_name }}
                            &middot; {{ auth()->user()->employee_number }}
                        </span>
                    </div>
                    <button type="button" wire:click="closePreview" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="ts-preview">
                        <div class="ts-preview-head">
                            <div><strong>Employee:</strong> {{ auth()->user()->full_name }}</div>
                            <div><strong>Employee no:</strong> {{ auth()->user()->employee_number }}</div>
                            <div><strong>Period:</strong> {{ \Carbon\Carbon::parse($monthYear)->format('F Y') }}</div>
                            <div><strong>Status:</strong> {{ $timesheet ? ucfirst(str_replace('_', ' ', $timesheet['status'])) : 'Draft' }}</div>
                        </div>

                        <table class="ts-preview-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th>Project / Task</th>
                                    <th>Description</th>
                                    <th style="text-align: right;">Hours</th>
                                    <th style="text-align: right;">Overtime</th>
                                    <th style="text-align: right;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entries as $entry)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($entry['work_date'])->format('d M Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($entry['work_date'])->format('D') }}</td>
                                        <td>{{ $entry['project_name'] ?: '—' }}</td>
                                        <td>{{ $entry['task_description'] ?: '—' }}</td>
                                        <td style="text-align: right;">{{ number_format((float) $entry['hours_worked'], 2) }}</td>
                                        <td style="text-align: right;">{{ number_format((float) $entry['overtime_hours'], 2) }}</td>
                                        <td style="text-align: right;">
                                            <strong>{{ number_format((float) $entry['hours_worked'] + (float) $entry['overtime_hours'], 2) }}</strong>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" style="font-weight: 700;">Totals</td>
                                    <td style="text-align: right; font-weight: 700;">{{ number_format($this->regularHours, 2) }}</td>
                                    <td style="text-align: right; font-weight: 700;">{{ number_format($this->overtimeHours, 2) }}</td>
                                    <td style="text-align: right; font-weight: 700;">{{ number_format($this->totalHours, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>

                        @if (! empty($notes))
                            <div class="ts-preview-notes">
                                <strong>Notes</strong>
                                <p>{{ $notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closePreview" class="nks-btn nks-btn--outline">Close</button>
                    <button type="button" onclick="window.print()" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-print"></i>
                        Print
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- AI Fill --}}
    @if ($showAiFill)
        <div class="nks-modal-backdrop" wire:click.self="closeAiFill" @keydown.escape.window="$wire.closeAiFill()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <div>
                        <h3>Fill timesheet with AI</h3>
                        <span class="nks-modal-sub">
                            Describe your month in plain English. The AI drafts entries you can review before applying.
                        </span>
                    </div>
                    <button type="button" wire:click="closeAiFill" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>

                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">What did you work on this month?</label>
                        <textarea wire:model.live="aiPrompt"
                                  class="nks-textarea"
                                  style="min-height: 140px;"
                                  placeholder="Example: I worked on the NKS client portal most days, refactored the billing module and did some UI polish. Two days I was on the internal reporting project building the KPI dashboard."></textarea>
                        <span class="nks-help">
                            The AI sees your assigned projects, your available working days this month, and your leave days. It will not create entries on weekends or leave days.
                        </span>
                    </div>

                    @if (count($aiPreview) > 0)
                        <div class="ts-ai-preview">
                            <div class="ts-ai-preview-head">
                                <strong>{{ count($aiPreview) }} {{ \Illuminate\Support\Str::plural('entry', count($aiPreview)) }} ready</strong>
                                <span>Review below, then click Apply</span>
                            </div>
                            <div class="ts-ai-preview-list">
                                @foreach ($aiPreview as $row)
                                    <div class="ts-ai-preview-row">
                                        <span class="ts-ai-date">{{ \Carbon\Carbon::parse($row['work_date'])->format('d M') }}</span>
                                        <span class="ts-ai-desc">{{ $row['task_description'] }}</span>
                                        <span class="ts-ai-hours">{{ $row['hours_worked'] }}h</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeAiFill" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="generateWithAi"
                            wire:loading.attr="disabled"
                            wire:target="generateWithAi"
                            class="nks-btn nks-btn--outline">
                        <i class="fa-solid fa-wand-magic-sparkles" wire:loading.remove wire:target="generateWithAi"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="generateWithAi"></span>
                        {{ count($aiPreview) > 0 ? 'Regenerate' : 'Generate' }}
                    </button>
                    @if (count($aiPreview) > 0)
                        <button type="button" wire:click="applyAiPreview" class="nks-btn nks-btn--primary">
                            <i class="fa-solid fa-check"></i>
                            Apply {{ count($aiPreview) }} {{ \Illuminate\Support\Str::plural('entry', count($aiPreview)) }}
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif
</div>