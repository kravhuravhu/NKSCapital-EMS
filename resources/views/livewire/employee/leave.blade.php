<div class="page-leave">

    @if ($error)
        <div class="lv-flash lv-flash--error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    @if ($flash)
        <div class="lv-flash lv-flash--success">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ $flash }}</span>
        </div>
    @endif

    <div class="lv-layout">

        {{-- LEFT: form + notice --}}
        <div class="lv-left">

            <div class="panel lv-form">
                <div class="lv-form-header">
                    <i class="fa-solid fa-umbrella-beach lv-form-icon"></i>
                    <div>
                        <h2>Apply for Leave</h2>
                        <p>Submit your leave request and track its status</p>
                    </div>
                </div>

                {{-- form grid --}}
                <div class="lv-grid">

                    <div class="nks-field">
                        <label class="nks-label">
                            Leave Type <span class="nks-required">*</span>
                        </label>
                        <select wire:model.live="leaveType" class="nks-select">
                            @foreach ($this->leaveTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Date Range <span class="nks-required">*</span>
                        </label>
                        <div class="lv-date-range">
                            <input type="date"
                                   wire:model.live="startDate"
                                   class="nks-input">
                            <span class="lv-date-sep">—</span>
                            <input type="date"
                                   wire:model.live="endDate"
                                   class="nks-input"
                                   @if($isHalfDay) disabled @endif>
                        </div>
                        <span class="nks-help">{{ $this->daysTaken }} working {{ \Illuminate\Support\Str::plural('day', $this->daysTaken) }} selected</span>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Number of Days</label>
                        <input type="text"
                               value="{{ $this->daysTaken }}"
                               class="nks-input"
                               readonly>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Half Day?</label>
                        <div class="lv-halfday">
                            <button type="button"
                                    wire:click="$toggle('isHalfDay')"
                                    class="nks-toggle {{ $isHalfDay ? 'is-on' : '' }}"
                                    role="switch"
                                    aria-checked="{{ $isHalfDay ? 'true' : 'false' }}">
                                <span class="nks-toggle-knob"></span>
                            </button>
                            <small class="nks-help">Request for a half day</small>
                        </div>
                    </div>

                    <div class="nks-field lv-full">
                        <label class="nks-label">
                            Reason for Leave <span class="nks-required">*</span>
                        </label>
                        <textarea wire:model.live="reason"
                                  maxlength="500"
                                  class="nks-textarea"
                                  placeholder="Family trips and personal time"></textarea>
                        <span class="lv-char-count">{{ strlen($reason) }} / 500 characters</span>
                    </div>

                    <div class="nks-field lv-full">
                        <label class="nks-label">
                            Hand Over / Work Delegation <span class="lv-optional">(Optional)</span>
                        </label>
                        <textarea wire:model="handover"
                                  maxlength="500"
                                  class="nks-textarea"
                                  placeholder="All task updates completed. Ongoing tasks delegated to …"></textarea>
                        <span class="lv-char-count">{{ strlen($handover) }} / 500 characters</span>
                    </div>

                    <div class="nks-field lv-full">
                        <label class="nks-label">Supporting Document <span class="lv-optional">(Optional)</span></label>
                        <label class="lv-upload">
                            <input type="file"
                                   wire:model="attachment"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   class="lv-file-input">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <div class="lv-upload-text">
                                @if ($attachment)
                                    {{ $attachment->getClientOriginalName() }}
                                @else
                                    Drag and drop your file here, or <em>click to upload</em>
                                @endif
                                <small>PDF, JPG, JPEG, PNG up to 10MB</small>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- conflict warning --}}
                @if (!empty($this->conflicts))
                    <div class="lv-conflict">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <div>
                            <strong>Conflict detected</strong>
                            <p>
                                @foreach ($this->conflicts as $c)
                                    {{ $c['name'] ?? 'A teammate' }} is already on leave for {{ $c['days'] ?? 0 }} day(s).
                                @endforeach
                                Your manager may need to override this.
                            </p>
                        </div>
                    </div>
                @endif

                {{-- actions --}}
                <div class="lv-actions">
                    <button type="button" wire:click="resetForm" class="nks-btn nks-btn--outline">
                        <i class="fa-solid fa-xmark"></i>
                        Cancel
                    </button>

                    <button type="button" wire:click="submit" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-paper-plane"></i>
                        Submit Leave Request
                    </button>
                </div>
            </div>

            <div class="lv-notice">
                <i class="fa-solid fa-circle-info"></i>
                <span>You will receive a notification once your request has been reviewed.</span>
            </div>
        </div>

        {{-- RIGHT: balance + policy --}}
        <div class="lv-right">

            <div class="panel">
                <h2>Leave Balance Summary</h2>

                <div class="lv-balance-list">
                    @foreach ($balances as $type => $data)
                        <div class="lv-balance-row">
                            <div class="lv-balance-icon">
                                <i class="fa-solid {{
                                    $type === 'annual'    ? 'fa-umbrella-beach' :
                                    ($type === 'sick'     ? 'fa-thermometer-half' :
                                    ($type === 'family'   ? 'fa-users' :
                                    ($type === 'unpaid'   ? 'fa-coins' :
                                    ($type === 'study'    ? 'fa-graduation-cap' :
                                    ($type === 'maternity'? 'fa-baby' :
                                    ($type === 'paternity'? 'fa-person-breastfeeding' : 'fa-calendar'))))))
                                }}"></i>
                            </div>

                            <div class="lv-balance-info">
                                <div class="lv-balance-name">{{ ucfirst($type) }} Leave</div>
                                <div class="lv-balance-days">
                                    {{ $data['available'] ?? 0 }} Days
                                </div>
                                <div class="lv-balance-sub">Available</div>
                            </div>

                            <div class="lv-balance-entitlement">
                                <div class="lv-balance-total">
                                    {{ is_numeric($data['entitlement'] ?? null) ? $data['entitlement'] . ' Days' : ($data['entitlement'] ?? '—') }}
                                </div>
                                <div class="lv-balance-label">Total Entitlement</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="lv-leave-year">
                    <i class="fa-solid fa-calendar-days"></i>
                    <strong>Leave Year:</strong> {{ now()->startOfYear()->format('d M Y') }} — {{ now()->endOfYear()->format('d M Y') }}
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
                            <div>
                                <div class="lv-policy-desc">No leave policies have been published yet.</div>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>