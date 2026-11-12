<div class="page-offers">
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
            <div class="pl-page-stat-icon"><i class="fa-solid fa-file-signature" aria-hidden="true"></i></div>
            <div>
                <div class="pl-page-stat-label">Total</div>
                <div class="pl-page-stat-value">{{ count($offers) }}</div>
            </div>
        </div>
        <div class="pl-page-stat pl-page-stat--warning">
            <div class="pl-page-stat-icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></div>
            <div>
                <div class="pl-page-stat-label">Extended</div>
                <div class="pl-page-stat-value">{{ collect($offers)->where('status', 'extended')->count() }}</div>
            </div>
        </div>
        <div class="pl-page-stat pl-page-stat--success">
            <div class="pl-page-stat-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
            <div>
                <div class="pl-page-stat-label">Accepted</div>
                <div class="pl-page-stat-value">{{ collect($offers)->where('status', 'accepted')->count() }}</div>
            </div>
        </div>
        <div class="pl-page-stat pl-page-stat--danger">
            <div class="pl-page-stat-icon"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></div>
            <div>
                <div class="pl-page-stat-label">Declined</div>
                <div class="pl-page-stat-value">{{ collect($offers)->where('status', 'declined')->count() }}</div>
            </div>
        </div>
        <div class="pl-page-stat pl-page-stat--info">
            <div class="pl-page-stat-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div>
            <div>
                <div class="pl-page-stat-label">Acceptance rate</div>
                <div class="pl-page-stat-value">
                    @php
                        $totalOffers = count($offers);
                        $accepted = collect($offers)->where('status', 'accepted')->count();
                        $rate = $totalOffers > 0 ? round(($accepted / $totalOffers) * 100) : 0;
                    @endphp
                    {{ $rate }}%
                </div>
            </div>
        </div>
    </div>

    <div class="panel of-panel">
        <div class="of-header">
            <i class="fa-solid fa-file-signature of-header-icon"></i>
            <div>
                <h2>Offers</h2>
                <p>Generate, send, track, and close offers of employment</p>
            </div>
            <button type="button" wire:click="openGenerate" class="nks-btn nks-btn--primary of-create-btn">
                <i class="fa-solid fa-plus"></i>
                Generate Offer
            </button>
        </div>

        <nav class="nks-tabs of-status-tabs">
            <button type="button"
                    wire:click="selectStatus('')"
                    class="nks-tab {{ $statusFilter === '' ? 'is-active' : '' }}">
                All
            </button>
            <button type="button"
                    wire:click="selectStatus('extended')"
                    class="nks-tab {{ $statusFilter === 'extended' ? 'is-active' : '' }}">
                Extended
            </button>
            <button type="button"
                    wire:click="selectStatus('accepted')"
                    class="nks-tab {{ $statusFilter === 'accepted' ? 'is-active' : '' }}">
                Accepted
            </button>
            <button type="button"
                    wire:click="selectStatus('declined')"
                    class="nks-tab {{ $statusFilter === 'declined' ? 'is-active' : '' }}">
                Declined
            </button>
            <button type="button"
                    wire:click="selectStatus('withdrawn')"
                    class="nks-tab {{ $statusFilter === 'withdrawn' ? 'is-active' : '' }}">
                Withdrawn
            </button>
            <button type="button"
                    wire:click="selectStatus('expired')"
                    class="nks-tab {{ $statusFilter === 'expired' ? 'is-active' : '' }}">
                Expired
            </button>
        </nav>

        <div class="of-filters">
            <div class="nks-field of-filter-search">
                <label class="nks-label">Search</label>
                <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                       class="nks-input" placeholder="Candidate name or email">
            </div>
            <div class="nks-field">
                <label class="nks-label">Requisition</label>
                <select wire:model="requisitionFilter" class="nks-select">
                    <option value="">Any requisition</option>
                    @foreach ($requisitions as $r)
                        <option value="{{ $r['id'] }}">
                            {{ $r['requisition_code'] ?? '' }} — {{ $r['title'] ?? '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="of-filter-actions">
                <button type="button" wire:click="resetFilters" class="nks-btn nks-btn--ghost nks-btn--sm">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </button>
                <button type="button" wire:click="applyFilters" class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-filter"></i> Apply
                </button>
            </div>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading offers…" />
        @elseif (count($offers) === 0)
            <div class="of-empty">
                <i class="fa-solid fa-file-signature"></i>
                <strong>No offers found</strong>
                <span>Adjust filters, or generate a new offer to get started.</span>
            </div>
        @else
            <div class="of-table-wrapper">
                <table class="of-table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Requisition</th>
                            <th>Position</th>
                            <th>Salary</th>
                            <th>Start</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th style="width: 300px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($offers as $o)
                            @php
                                $status = $o['status'] ?? 'extended';
                                $expiringSoon = !empty($o['days_until_expiry']) && $o['days_until_expiry'] >= 0 && $o['days_until_expiry'] <= 3 && $status === 'extended';
                            @endphp
                            <tr wire:key="of-{{ $o['id'] }}">
                                <td>
                                    <div class="of-name">
                                        {{ $o['candidate']['first_name'] ?? '' }} {{ $o['candidate']['last_name'] ?? '' }}
                                    </div>
                                    <div class="of-meta">{{ $o['candidate']['email'] ?? '' }}</div>
                                </td>
                                <td>
                                    @if (!empty($o['candidate']['requisition']))
                                        <div class="of-name">{{ $o['candidate']['requisition']['title'] ?? '—' }}</div>
                                        <div class="of-meta">{{ $o['candidate']['requisition']['requisition_code'] ?? '' }}</div>
                                    @else — @endif
                                </td>
                                <td>{{ $o['position'] ?? '—' }}</td>
                                <td>
                                    @if (!empty($o['salary_offered']))
                                        R {{ number_format((float) $o['salary_offered'], 2) }}
                                    @else — @endif
                                </td>
                                <td>
                                    {{ !empty($o['start_date']) ? \Carbon\Carbon::parse($o['start_date'])->format('d M Y') : '—' }}
                                </td>
                                <td>
                                    @if (!empty($o['expiry_date']))
                                        <div class="{{ $expiringSoon ? 'of-expiring' : '' }}">
                                            {{ \Carbon\Carbon::parse($o['expiry_date'])->format('d M Y') }}
                                        </div>
                                        @if ($expiringSoon)
                                            <div class="of-meta">{{ (int) $o['days_until_expiry'] }} days left</div>
                                        @endif
                                    @else — @endif
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Recruiter\Offers::statusTone($status)">
                                        {{ ucfirst($status) }}
                                    </x-nks.badge>
                                    @if (!empty($o['sent_at']))
                                        <div class="of-meta">Emailed</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="of-row-actions">
                                        <button type="button" wire:click="openView({{ $o['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>

                                        @if ($this->canSendEmail($o))
                                            <button type="button" wire:click="openSendEmail({{ $o['id'] }})"
                                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                                <i class="fa-solid fa-envelope"></i> Email
                                            </button>
                                        @endif

                                        @if ($this->canAccept($o))
                                            <button type="button" wire:click="openAccept({{ $o['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-check"></i> Accept
                                            </button>
                                        @endif

                                        @if ($this->canDecline($o))
                                            <button type="button" wire:click="openDecline({{ $o['id'] }})"
                                                    class="nks-btn nks-btn--danger nks-btn--sm">
                                                <i class="fa-solid fa-xmark"></i> Decline
                                            </button>
                                        @endif

                                        @if ($this->canWithdraw($o))
                                            <button type="button" wire:click="openWithdraw({{ $o['id'] }})"
                                                    class="of-icon-btn"
                                                    title="Withdraw">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        @endif

                                         @if ($this->canConvert($o))
                                            <a href="{{ route('portal.recruiter.convert', ['offer_id' => $o['id']]) }}"
                                            wire:navigate
                                            class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-user-plus"></i> Convert
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (($pagination['last_page'] ?? 1) > 1)
                <div class="of-pagination">
                    <button type="button" wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] <= 1) disabled @endif>
                        <i class="fa-solid fa-chevron-left"></i> Previous
                    </button>
                    <span class="of-page-info">
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
    </div>

    {{-- generate --}}
    @if ($showGenerate)
        <div class="of-modal-backdrop" wire:click.self="closeGenerate">
            <div class="of-modal of-modal--lg">
                <header class="of-modal-head">
                    <h3>Generate Offer</h3>
                    <button type="button" wire:click="closeGenerate" class="of-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="of-modal-body">
                    <div class="of-form-grid">
                        <div class="nks-field of-form-full">
                            <label class="nks-label">Candidate <span class="nks-required">*</span></label>
                            <select wire:model="generateForm.candidate_id" class="nks-select">
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
                            <span class="nks-help">
                                Any candidate not already hired, rejected, or withdrawn can be offered. Generating advances them to the offer stage.
                            </span>
                        </div>

                        <div class="nks-field">
                            <label class="nks-label">Salary Offered (R) <span class="nks-required">*</span></label>
                            <input type="number" wire:model="generateForm.salary_offered" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Contract Type</label>
                            <select wire:model="generateForm.contract_type" class="nks-select">
                                <option value="permanent">Permanent</option>
                                <option value="fixed_term">Fixed Term</option>
                                <option value="consulting">Consulting</option>
                                <option value="internship">Internship</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Position</label>
                            <input type="text" wire:model="generateForm.position" class="nks-input"
                                   placeholder="Inherits from requisition if blank">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Department</label>
                            <input type="text" wire:model="generateForm.department" class="nks-input"
                                   placeholder="Inherits from requisition if blank">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Start Date <span class="nks-required">*</span></label>
                            <input type="date" wire:model="generateForm.start_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Offer Expiry <span class="nks-required">*</span></label>
                            <input type="date" wire:model="generateForm.expiry_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Notice Period (days)</label>
                            <input type="number" wire:model="generateForm.notice_period_days" class="nks-input" min="0" max="180">
                        </div>
                        <div class="nks-field of-form-full">
                            <label class="nks-label">Benefits</label>
                            <textarea wire:model="generateForm.benefits" class="nks-textarea"
                                      placeholder="One per line or comma-separated: Medical aid, Pension, 20 days leave"></textarea>
                            <span class="nks-help">Each benefit will appear as a bullet on the offer letter.</span>
                        </div>
                    </div>
                </div>
                <footer class="of-modal-foot">
                    <button type="button" wire:click="closeGenerate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitGenerate" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-file-signature"></i> Generate Offer
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- accept --}}
    @if ($showAccept)
        <div class="of-modal-backdrop" wire:click.self="closeAccept">
            <div class="of-modal">
                <header class="of-modal-head">
                    <h3>Accept Offer</h3>
                    <button type="button" wire:click="closeAccept" class="of-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="of-modal-body">
                    <p class="of-text">
                        Confirm that <strong>{{ $acceptName }}</strong> has accepted the offer.
                        Optionally attach the signed copy for the record.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Signed Copy (optional)</label>
                        <input type="file" wire:model="signedCopy" accept="application/pdf"
                               class="nks-input" style="height: auto; padding: 8px;">
                        <span class="nks-help">
                            If provided, the file is stored and its SHA-256 is captured. Only PDF, up to 20MB.
                        </span>
                    </div>
                </div>
                <footer class="of-modal-foot">
                    <button type="button" wire:click="closeAccept" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitAccept"
                            wire:loading.attr="disabled" wire:target="submitAccept"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i> Accept Offer
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- decline --}}
    @if ($showDecline)
        <div class="of-modal-backdrop" wire:click.self="closeDecline">
            <div class="of-modal">
                <header class="of-modal-head">
                    <h3>Decline Offer</h3>
                    <button type="button" wire:click="closeDecline" class="of-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="of-modal-body">
                    <p class="of-text">
                        Record that <strong>{{ $declineName }}</strong> has declined the offer.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Reason <span class="nks-required">*</span></label>
                        <textarea wire:model="declineReason" class="nks-textarea"
                                  placeholder="Reason the candidate gave for declining."></textarea>
                    </div>
                </div>
                <footer class="of-modal-foot">
                    <button type="button" wire:click="closeDecline" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitDecline" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-xmark"></i> Confirm Decline
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- withdraw --}}
    @if ($showWithdraw)
        <div class="of-modal-backdrop" wire:click.self="closeWithdraw">
            <div class="of-modal">
                <header class="of-modal-head">
                    <h3>Withdraw Offer</h3>
                    <button type="button" wire:click="closeWithdraw" class="of-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="of-modal-body">
                    <p class="of-text">
                        Withdraw the offer to <strong>{{ $withdrawName }}</strong>?
                        This is a recruiter-side action — the candidate did not decline.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Reason <span class="nks-required">*</span></label>
                        <textarea wire:model="withdrawReason" class="nks-textarea"
                                  placeholder="Business reason for withdrawing the offer."></textarea>
                    </div>
                </div>
                <footer class="of-modal-foot">
                    <button type="button" wire:click="closeWithdraw" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitWithdraw" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-rotate-left"></i> Withdraw Offer
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- send email --}}
    @if ($showSendEmail)
        <div class="of-modal-backdrop" wire:click.self="closeSendEmail">
            <div class="of-modal">
                <header class="of-modal-head">
                    <h3>Send Offer Email</h3>
                    <button type="button" wire:click="closeSendEmail" class="of-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="of-modal-body">
                    <p class="of-text">
                        Send the offer letter PDF to <strong>{{ $sendEmailName }}</strong>.
                        The PDF is attached automatically.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Custom Message (optional)</label>
                        <textarea wire:model="sendEmailMessage" class="nks-textarea"
                                  placeholder="Leave blank to use the standard template."></textarea>
                    </div>
                </div>
                <footer class="of-modal-foot">
                    <button type="button" wire:click="closeSendEmail" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitSendEmail"
                            wire:loading.attr="disabled" wire:target="submitSendEmail"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-envelope"></i> Send Email
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- view --}}
    @if ($showView && $viewData)
        @php $vStatus = $viewData['status'] ?? ''; @endphp
        <div class="of-modal-backdrop" wire:click.self="closeView">
            <div class="of-modal of-modal--lg">
                <header class="of-modal-head">
                    <div>
                        <h3>
                            {{ $viewData['candidate']['first_name'] ?? '' }}
                            {{ $viewData['candidate']['last_name'] ?? '' }}
                            @if (!empty($viewData['position']))
                                — {{ $viewData['position'] }}
                            @endif
                        </h3>
                        <span class="of-modal-sub">{{ $viewData['candidate']['email'] ?? '' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="of-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="of-modal-body">
                    <div class="of-detail-badges">
                        <x-nks.badge :tone="\App\Livewire\Recruiter\Offers::statusTone($vStatus)">
                            {{ ucfirst($vStatus) }}
                        </x-nks.badge>
                        @if (!empty($viewData['sent_at']))
                            <x-nks.badge tone="muted">
                                Emailed {{ \Carbon\Carbon::parse($viewData['sent_at'])->diffForHumans() }}
                            </x-nks.badge>
                        @endif
                        @if (!empty($viewData['signed_copy_path']))
                            <x-nks.badge tone="success">Signed copy attached</x-nks.badge>
                        @endif
                    </div>

                    <div class="of-detail-grid">
                        <div>
                            <span class="of-detail-label">Salary Offered</span>
                            <span class="of-detail-value">
                                @if (!empty($viewData['salary_offered']))
                                    R {{ number_format((float) $viewData['salary_offered'], 2) }}
                                @else — @endif
                            </span>
                        </div>
                        <div>
                            <span class="of-detail-label">Contract Type</span>
                            <span class="of-detail-value">
                                {{ ucfirst(str_replace('_', ' ', $viewData['contract_type'] ?? '—')) }}
                            </span>
                        </div>
                        <div>
                            <span class="of-detail-label">Department</span>
                            <span class="of-detail-value">{{ $viewData['department'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="of-detail-label">Manager</span>
                            <span class="of-detail-value">
                                @if (!empty($viewData['manager']))
                                    {{ $viewData['manager']['first_name'] ?? '' }}
                                    {{ $viewData['manager']['last_name'] ?? '' }}
                                @else — @endif
                            </span>
                        </div>
                        <div>
                            <span class="of-detail-label">Start Date</span>
                            <span class="of-detail-value">
                                {{ !empty($viewData['start_date']) ? \Carbon\Carbon::parse($viewData['start_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="of-detail-label">Offer Expiry</span>
                            <span class="of-detail-value">
                                {{ !empty($viewData['expiry_date']) ? \Carbon\Carbon::parse($viewData['expiry_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="of-detail-label">Notice Period</span>
                            <span class="of-detail-value">
                                {{ !empty($viewData['notice_period_days']) ? $viewData['notice_period_days'] . ' days' : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="of-detail-label">Created By</span>
                            <span class="of-detail-value">
                                {{ $viewData['created_by']['first_name'] ?? '—' }}
                                {{ $viewData['created_by']['last_name'] ?? '' }}
                            </span>
                        </div>
                    </div>

                    @if (!empty($viewData['benefits']) && is_array($viewData['benefits']))
                        <div class="of-detail-section">
                            <span class="of-detail-label">Benefits</span>
                            <ul class="of-benefits">
                                @foreach ($viewData['benefits'] as $benefit)
                                    <li>
                                        <i class="fa-solid fa-circle-check"></i>
                                        {{ is_array($benefit) ? ($benefit['name'] ?? json_encode($benefit)) : $benefit }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (!empty($viewData['declined_reason']))
                        <div class="of-detail-section">
                            <span class="of-detail-label">Decline Reason</span>
                            <p class="of-detail-text of-detail-text--danger">{{ $viewData['declined_reason'] }}</p>
                        </div>
                    @endif

                    @if (!empty($viewData['withdrawn_reason']))
                        <div class="of-detail-section">
                            <span class="of-detail-label">Withdrawal Reason</span>
                            <p class="of-detail-text">{{ $viewData['withdrawn_reason'] }}</p>
                        </div>
                    @endif

                    <div class="of-detail-section">
                        <span class="of-detail-label">Documents</span>
                        <div class="of-file-row">
                            @if (!empty($viewData['offer_letter_path']))
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($viewData['offer_letter_path']) }}"
                                   target="_blank" rel="noopener"
                                   class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-file-pdf"></i> Offer Letter PDF
                                </a>
                            @endif
                            @if (!empty($viewData['signed_copy_path']))
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($viewData['signed_copy_path']) }}"
                                   target="_blank" rel="noopener"
                                   class="nks-btn nks-btn--primary nks-btn--sm">
                                    <i class="fa-solid fa-file-signature"></i> Signed Copy
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                <footer class="of-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    @if ($this->canAccept($viewData))
                        <button type="button" wire:click="openAccept({{ $viewData['id'] }})" class="nks-btn nks-btn--primary">
                            <i class="fa-solid fa-check"></i> Accept
                        </button>
                    @endif
                    @if ($this->canSendEmail($viewData))
                        <button type="button" wire:click="openSendEmail({{ $viewData['id'] }})" class="nks-btn nks-btn--outline">
                            <i class="fa-solid fa-envelope"></i> Send Email
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif
</div>