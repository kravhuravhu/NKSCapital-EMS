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

    <div class="panel ts-panel">

        {{-- header --}}
        <div class="ts-header">
            <i class="fa-solid fa-clipboard-list ts-header-icon" aria-hidden="true"></i>

            <h2 class="ts-header-title">
                {{ $monthYear ? \Carbon\Carbon::parse($monthYear)->format('F Y') : now()->format('F Y') }} Timesheet
            </h2>

            <span class="nks-badge nks-badge--{{ $timesheet ? 'gold' : 'muted' }} ts-header-status">
                Status: {{ $timesheet ? ucfirst(str_replace('_', ' ', $timesheet['status'])) : 'Draft' }}
            </span>

            <span class="ts-header-deadline">
                Deadline: {{ \Carbon\Carbon::parse($monthYear)->endOfMonth()->format('d F Y') }}
            </span>

            <input type="month"
                   wire:model.live="monthYear"
                   class="ts-header-month">
        </div>

        {{-- metrics --}}
        <div class="ts-metrics">
            <div class="ts-metric">
                <div class="ts-metric-label">Regular hours</div>
                <div class="ts-metric-value">
                    {{ \App\Livewire\Employee\Timesheet::formatHours($this->regularHours) }}
                </div>
            </div>

            <div class="ts-metric">
                <div class="ts-metric-label">Overtime</div>
                <div class="ts-metric-value ts-metric-value--overtime">
                    {{ \App\Livewire\Employee\Timesheet::formatHours($this->overtimeHours) }}
                </div>
            </div>

            <div class="ts-metric">
                <div class="ts-metric-label">Leave Taken</div>
                <div class="ts-metric-value ts-metric-value--leave">
                    {{ number_format($this->leaveTaken, 1) }} Days
                </div>
            </div>

            <div class="ts-metric">
                <div class="ts-metric-label">Total Hours</div>
                <div class="ts-metric-value">
                    {{ \App\Livewire\Employee\Timesheet::formatHours($this->totalHours) }}
                </div>
            </div>

            <div class="ts-metric ts-metric--progress">
                <div class="ts-progress-head">
                    <div class="ts-progress-labels">
                        <span class="ts-metric-label">Progress:</span>
                        <span class="ts-metric-sub">
                            {{ $this->workingDaysCompleted }} of {{ $this->workingDaysTarget }} working days completed
                        </span>
                    </div>
                    <span class="ts-progress-percent">{{ $this->progressPercent }}%</span>
                </div>
                <div class="ts-progress-bar">
                    <div class="ts-progress-fill" style="width: {{ $this->progressPercent }}%"></div>
                </div>
            </div>
        </div>

        {{-- entries table --}}
        <div class="ts-table-wrapper">
            <table class="ts-table">
                <colgroup>
                    <col style="width: 9%">
                    <col style="width: 5%">
                    <col style="width: 14%">
                    <col style="width: 24%">
                    <col style="width: 10%">
                    <col style="width: 14%">
                    <col style="width: 10%">
                    <col style="width: 14%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Task/Project</th>
                        <th>Task Description</th>
                        <th>Hours</th>
                        <th>Hours (Overtime)</th>
                        <th>Total Hours</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $index => $entry)
                        <tr wire:key="entry-{{ $index }}-{{ $entry['work_date'] }}">
                            <td class="ts-td-date">
                                {{ $entry['work_date'] ? \Carbon\Carbon::parse($entry['work_date'])->format('d M Y') : '—' }}
                            </td>
                            <td class="ts-td-day">
                                {{ $entry['work_date'] ? \Carbon\Carbon::parse($entry['work_date'])->format('D') : '—' }}
                            </td>
                            <td>
                                <input type="text"
                                       wire:model="entries.{{ $index }}.project_name"
                                       class="ts-cell-input"
                                       placeholder="Task/Project">
                            </td>
                            <td>
                                <input type="text"
                                       wire:model="entries.{{ $index }}.task_description"
                                       class="ts-cell-input"
                                       placeholder="Task description">
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
                                {{ \App\Livewire\Employee\Timesheet::formatHours((float) $entry['hours_worked'] + (float) $entry['overtime_hours']) }}
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
                                            title="Clear or delete row">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="ts-empty">
                                <i class="fa-solid fa-circle-info"></i>
                                No entries yet. Click <strong>Add Row</strong> to begin.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- table footer --}}
        <div class="ts-footer">
            <button type="button"
                    wire:click="addRow"
                    class="nks-btn nks-btn--outline nks-btn--sm">
                <i class="fa-solid fa-plus"></i>
                Add Row
            </button>

            <span class="ts-footer-spacer"></span>

            <span class="ts-footer-label">Total</span>
            <span class="ts-footer-value">
                {{ \App\Livewire\Employee\Timesheet::formatHours($this->regularHours) }}
            </span>
            <span class="ts-footer-value">
                {{ \App\Livewire\Employee\Timesheet::formatHours($this->overtimeHours) }}
            </span>
            <span class="ts-footer-value">
                {{ \App\Livewire\Employee\Timesheet::formatHours($this->totalHours) }}
            </span>
        </div>

        {{-- bottom: notes + actions --}}
        <div class="ts-bottom">
            <div class="ts-bottom-block">
                <h3 class="ts-bottom-title">
                    Additional Notes <span>(Optional)</span>
                </h3>
                <textarea wire:model="notes"
                          class="ts-bottom-textarea"
                          placeholder="Add any notes or comments for this timesheet"></textarea>
            </div>

            <div class="ts-bottom-block">
                <h3 class="ts-bottom-title">Actions</h3>

                <div class="ts-actions">
                    <a href="{{ route('portal.' . auth()->user()->role . '.timesheet') }}"
                       class="nks-btn nks-btn--outline nks-btn--sm">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        View Timesheet History
                    </a>

                    <button type="button" class="nks-btn nks-btn--outline nks-btn--sm">
                        <i class="fa-solid fa-eye"></i>
                        Preview Timesheet
                    </button>

                    @if ($workflow === 'ps')
                        @if (!$timesheet)
                            <button type="button" wire:click="createTimesheet"
                                    class="nks-btn nks-btn--primary nks-btn--sm nks-btn--block">
                                <i class="fa-solid fa-plus"></i>
                                Create Timesheet
                            </button>
                        @else
                            <button type="button" wire:click="generateTemplate"
                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                <i class="fa-solid fa-file-lines"></i>
                                Generate Client Template
                            </button>

                            <a href="/api/v1/timesheet/ps/download-template?timesheet_id={{ $timesheet['id'] }}"
                               class="nks-btn nks-btn--primary nks-btn--sm" target="_blank">
                                <i class="fa-solid fa-download"></i>
                                Download Template
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
                                <input type="email" wire:model="clientEmail"
                                       class="ts-cell-input"
                                       placeholder="Client manager email">
                                <button type="button" wire:click="sendToClient"
                                        class="nks-btn nks-btn--primary nks-btn--sm">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    Send to Client
                                </button>
                            </div>
                        @endif
                    @else
                        <button type="button" wire:click="saveDraft"
                                class="nks-btn nks-btn--outline nks-btn--sm">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Draft
                        </button>

                        <button type="button" wire:click="submitForApproval"
                                class="nks-btn nks-btn--primary nks-btn--sm nks-btn--block">
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
                    Your timesheet must be signed by the client. Once uploaded, it is emailed to the client manager and a copy is stored for the Director.
                @else
                    Your timesheet goes through two approvals — first the Project Manager, then the Director. Payroll receives the final approved copy automatically.
                @endif
            </span>
        </div>
    </div>
</div>