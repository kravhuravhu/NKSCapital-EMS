<div class="page-leave" wire:init="load">
    <x-nks.loading-overlay :loading="$loading" targets="load" />
    <x-nks.notice-stack :error="$error" :success="$flash" />

    @if ($loading)
        <div class="panel">
            <div class="nks-card-empty" style="padding: 60px 24px;">
                <i class="fa-solid fa-circle-notch fa-spin" style="color: var(--nks-gold);"></i>
                <p>Loading leave data...</p>
            </div>
        </div>
    @else
        @if ($this->halfDayBanner)
            <div class="nks-notice nks-notice--warning" style="margin-bottom: 16px;">
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                <div class="nks-notice-body">
                    <strong style="display: block;">
                        Half-day {{ $this->halfDayBanner['status'] === 'pending' ? 'request pending' : 'noted for today' }}
                    </strong>
                    <span>
                        You asked to leave at {{ substr($this->halfDayBanner['leave_time'], 0, 5) }}
                        on {{ \Carbon\Carbon::parse($this->halfDayBanner['notice_date'])->format('d M Y') }}.
                    </span>
                </div>
            </div>
        @endif

        <nav class="nks-tabs">
            <button type="button" wire:click="switchTab('apply')" class="nks-tab {{ $tab === 'apply' ? 'is-active' : '' }}">
                Apply
            </button>
            <button type="button" wire:click="switchTab('pending')" class="nks-tab {{ $tab === 'pending' ? 'is-active' : '' }}">
                Pending ({{ count($this->pendingRequests) }})
            </button>
            <button type="button" wire:click="switchTab('requests')" class="nks-tab {{ $tab === 'requests' ? 'is-active' : '' }}">
                All Requests
            </button>
            <button type="button" wire:click="switchTab('half-day')" class="nks-tab {{ $tab === 'half-day' ? 'is-active' : '' }}">
                Half Day
            </button>
        </nav>

        @if ($tab === 'apply')
            <div class="lv-layout">
                <div class="lv-left">
                    <div class="panel lv-form">
                        <div class="lv-form-header">
                            <i class="fa-solid fa-umbrella-beach lv-form-icon"></i>
                            <div>
                                <h2>Apply for Leave</h2>
                                <p>Submit a leave request and track its status</p>
                            </div>
                        </div>

                        <div class="lv-grid">
                            <div class="nks-field">
                                <label class="nks-label">Leave Type <span class="nks-required">*</span></label>
                                <select wire:model.live="leaveType" class="nks-select">
                                    @foreach ($this->leaveTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="nks-field">
                                <label class="nks-label">Date Range <span class="nks-required">*</span></label>
                                <div class="lv-date-range">
                                    <input type="date" wire:model.live="startDate" class="nks-input">
                                    <span class="lv-date-sep">to</span>
                                    <input type="date" wire:model.live="endDate" class="nks-input">
                                </div>
                                <span class="nks-help">{{ $this->daysTaken }} working {{ \Illuminate\Support\Str::plural('day', $this->daysTaken) }}</span>
                            </div>

                            <div class="nks-field lv-full">
                                <label class="nks-label">Reason <span class="nks-required">*</span></label>
                                <select wire:model.live="reasonOption" class="nks-select">
                                    @foreach ($this->reasonOptions as $option)
                                        <option value="{{ $option['label'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if ($this->isOtherReason)
                                <div class="nks-field lv-full">
                                    <label class="nks-label">Describe the reason <span class="nks-required">*</span></label>
                                    <textarea wire:model.live="reasonCustom" maxlength="500" class="nks-textarea"
                                              placeholder="Explain your reason for taking this leave."></textarea>
                                    <div class="lv-ai-row">
                                        <span class="lv-char-count">{{ strlen($reasonCustom) }} / 500</span>
                                        <button type="button" wire:click="refineReason"
                                                wire:loading.attr="disabled" wire:target="refineReason"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-wand-magic-sparkles" wire:loading.remove wire:target="refineReason"></i>
                                            <span class="nks-btn-spinner" wire:loading wire:target="refineReason"></span>
                                            Refine with AI
                                        </button>
                                    </div>
                                </div>
                            @endif

                            <div class="nks-field lv-full">
                                <label class="nks-label">Handover <span class="lv-optional">(Optional)</span></label>
                                <textarea wire:model="handover" class="nks-textarea"
                                          placeholder="Ongoing tasks delegated to..."></textarea>
                            </div>

                            <div class="nks-field lv-full">
                                <label class="nks-label">Supporting Document <span class="lv-optional">(Optional)</span></label>
                                <label class="lv-upload">
                                    <input type="file" wire:model="attachment" accept=".pdf,.jpg,.jpeg,.png" class="lv-file-input">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <div class="lv-upload-text">
                                        @if ($attachment)
                                            {{ $attachment->getClientOriginalName() }}
                                        @else
                                            Drag and drop your file, or <em>click to upload</em>
                                        @endif
                                        <small>PDF, JPG, PNG up to 10MB</small>
                                    </div>
                                </label>
                            </div>
                        </div>

                        @if (! empty($selfConflicts))
                            <div class="lv-conflict" style="background: #F5B5B5; color: #8A2A2A;">
                                <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
                                <div>
                                    <strong style="color: #8A2A2A;">You already have approved leave on these dates</strong>
                                    <p>
                                        @foreach ($selfConflicts as $c)
                                            {{ \Carbon\Carbon::parse($c['date'])->format('d M Y') }} ({{ ucfirst($c['leave_type']) }}){{ ! $loop->last ? ',' : '' }}
                                        @endforeach
                                    </p>
                                </div>
                            </div>
                        @endif

                        @if (! empty($conflicts))
                            <div class="lv-conflict">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <div>
                                    <strong>Team conflict</strong>
                                    <p>
                                        @foreach ($conflicts as $c)
                                            {{ $c['name'] ?? 'A teammate' }} is already on leave for {{ $c['conflicting_days'] ?? $c['days'] ?? 0 }} day(s).{{ ! $loop->last ? ' ' : '' }}
                                        @endforeach
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="lv-actions">
                            <button type="button" wire:click="resetForm" class="nks-btn nks-btn--outline">Cancel</button>
                            <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                                    class="nks-btn nks-btn--primary">
                                <i class="fa-solid fa-paper-plane" wire:loading.remove wire:target="submit"></i>
                                <span class="nks-btn-spinner" wire:loading wire:target="submit"></span>
                                Submit Leave Request
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lv-right">
                    <div class="panel">
                        <h2>Leave Balance Summary</h2>
                        <div class="lv-balance-list">
                            @foreach ($balances as $type => $data)
                                <div class="lv-balance-row">
                                    <div class="lv-balance-icon"><i class="fa-solid fa-circle"></i></div>
                                    <div class="lv-balance-info">
                                        <div class="lv-balance-name">{{ ucfirst($type) }} Leave</div>
                                        <div class="lv-balance-days">
                                            {{ is_numeric($data['available'] ?? null) ? $data['available'] : ($data['available'] ?? 0) }}
                                            {{ is_numeric($data['available'] ?? null) ? 'Days' : '' }}
                                        </div>
                                        <div class="lv-balance-sub">Available</div>
                                    </div>
                                    <div class="lv-balance-entitlement">
                                        <div class="lv-balance-total">
                                            {{ is_numeric($data['entitlement'] ?? null) ? $data['entitlement'] . ' Days' : ($data['entitlement'] ?? '-') }}
                                        </div>
                                        <div class="lv-balance-label">Total</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="panel">
                        <h2>Leave Policy</h2>
                        <div class="lv-policy-list">
                            @forelse ($policies as $policy)
                                <div class="lv-policy-row">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <div>
                                        <div class="lv-policy-title">{{ $policy['title'] }}</div>
                                        <div class="lv-policy-desc">{{ $policy['description'] }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="lv-policy-row">
                                    <i class="fa-solid fa-circle-info"></i>
                                    <div class="lv-policy-desc">No leave policies have been published yet.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($tab === 'pending')
            <div class="panel">
                <h2>Pending Leave Requests</h2>
                @if (count($this->pendingRequests) === 0)
                    <div class="nks-card-empty">
                        <i class="fa-solid fa-inbox"></i>
                        <p>You have no pending leave requests.</p>
                    </div>
                @else
                    <div class="nks-table-wrapper">
                        <table class="nks-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->pendingRequests as $request)
                                    <tr>
                                        <td>{{ ucfirst($request['leave_type']) }}</td>
                                        <td>
                                            {{ \Carbon\Carbon::parse($request['start_date'])->format('d M Y') }}
                                            to
                                            {{ \Carbon\Carbon::parse($request['end_date'])->format('d M Y') }}
                                        </td>
                                        <td>{{ $request['days_taken'] }}</td>
                                        <td>
                                            <x-nks.badge tone="gold">
                                                {{ ucfirst(str_replace('_', ' ', $request['status'])) }}
                                            </x-nks.badge>
                                        </td>
                                        <td>{{ isset($request['created_at']) ? \Carbon\Carbon::parse($request['created_at'])->format('d M Y') : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @elseif ($tab === 'requests')
            <div class="panel">
                <h2>My Leave Requests</h2>
                @if (count($myRequests) === 0)
                    <div class="nks-card-empty">
                        <i class="fa-solid fa-inbox"></i>
                        <p>You have not submitted any leave requests.</p>
                    </div>
                @else
                    <div class="nks-table-wrapper">
                        <table class="nks-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($myRequests as $request)
                                    <tr>
                                        <td>{{ ucfirst($request['leave_type']) }}</td>
                                        <td>
                                            {{ \Carbon\Carbon::parse($request['start_date'])->format('d M Y') }}
                                            to
                                            {{ \Carbon\Carbon::parse($request['end_date'])->format('d M Y') }}
                                        </td>
                                        <td>{{ $request['days_taken'] }}</td>
                                        <td>
                                            <x-nks.badge tone="{{ in_array($request['status'], ['approved', 'auto_approved'], true) ? 'success' : (in_array($request['status'], ['rejected', 'cancelled'], true) ? 'danger' : 'gold') }}">
                                                {{ ucfirst(str_replace('_', ' ', $request['status'])) }}
                                            </x-nks.badge>
                                        </td>
                                        <td>{{ isset($request['created_at']) ? \Carbon\Carbon::parse($request['created_at'])->format('d M Y') : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @elseif ($tab === 'half-day')
            <div class="panel">
                <div class="page-toolbar">
                    <div>
                        <h2>Half-day notices</h2>
                        <p>Ask to leave early. This does not deduct from your leave balance.</p>
                    </div>
                    <button type="button" wire:click="openHalfDay" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-plus"></i>
                        Request half day
                    </button>
                </div>

                @if (count($myHalfDays) === 0)
                    <div class="nks-card-empty">
                        <i class="fa-solid fa-clock"></i>
                        <p>No half-day notices yet.</p>
                    </div>
                @else
                    <div class="nks-table-wrapper">
                        <table class="nks-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Leave at</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Manager response</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($myHalfDays as $notice)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($notice['notice_date'])->format('d M Y') }}</td>
                                        <td>{{ substr($notice['leave_time'], 0, 5) }}</td>
                                        <td>{{ $notice['reason'] }}</td>
                                        <td>
                                            <x-nks.badge tone="{{ $notice['status'] === 'acknowledged' ? 'success' : ($notice['status'] === 'declined' ? 'danger' : 'gold') }}">
                                                {{ ucfirst($notice['status']) }}
                                            </x-nks.badge>
                                        </td>
                                        <td>{{ $notice['manager_response'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    @endif

    @if ($showHalfDay)
        <div class="nks-modal-backdrop" wire:click.self="closeHalfDay">
            <div class="nks-modal nks-modal--md">
                <header class="nks-modal-head">
                    <h3>Request half day</h3>
                    <button type="button" wire:click="closeHalfDay" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Date</label>
                        <input type="date" wire:model="halfDayForm.notice_date" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Leave at</label>
                        <input type="time" wire:model="halfDayForm.leave_time" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Reason</label>
                        <textarea wire:model="halfDayForm.reason" class="nks-textarea" maxlength="1000"></textarea>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeHalfDay" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitHalfDay" class="nks-btn nks-btn--primary"
                            wire:loading.attr="disabled" wire:target="submitHalfDay">
                        <i class="fa-solid fa-paper-plane" wire:loading.remove wire:target="submitHalfDay"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="submitHalfDay"></span>
                        Send to manager
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>