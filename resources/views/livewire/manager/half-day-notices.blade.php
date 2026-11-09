<div class="page-manager-half-day">
    @if ($error)
        <div class="nks-notice" style="background: rgba(194, 91, 91, 0.1); color: var(--nks-danger);">
            <i class="fa-solid fa-circle-exclamation" style="color: var(--nks-danger);"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($flash)
        <div class="nks-notice" style="background: var(--nks-mint); color: #1F6B45;">
            <i class="fa-solid fa-circle-check" style="color: #1F6B45;"></i>
            <div class="nks-notice-body">{{ $flash }}</div>
        </div>
    @endif

    <div class="panel">
        <div class="page-toolbar">
            <div>
                <h2>Half-day notices</h2>
                <p>Team members who have asked to leave early. Nothing is deducted from their leave balance.</p>
            </div>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading notices..." />
        @elseif (count($notices) === 0)
            <div class="nks-card-empty">
                <i class="fa-solid fa-clock"></i>
                <p>No pending half-day notices.</p>
            </div>
        @else
            <div class="nks-table-wrapper">
                <table class="nks-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Leave at</th>
                            <th>Reason</th>
                            <th style="width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($notices as $notice)
                            <tr wire:key="hd-{{ $notice['id'] }}">
                                <td>
                                    <strong>{{ $notice['user']['first_name'] ?? '' }} {{ $notice['user']['last_name'] ?? '' }}</strong>
                                    <div style="font-size: 11px; color: var(--nks-muted);">
                                        {{ $notice['user']['employee_number'] ?? '' }}
                                    </div>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($notice['notice_date'])->format('d M Y') }}</td>
                                <td>{{ substr($notice['leave_time'], 0, 5) }}</td>
                                <td>{{ $notice['reason'] }}</td>
                                <td>
                                    <div style="display: flex; gap: 4px;">
                                        <button type="button"
                                                wire:click="openRespond({{ $notice['id'] }}, 'acknowledged')"
                                                class="nks-btn nks-btn--primary nks-btn--sm">
                                            <i class="fa-solid fa-check"></i>
                                            Acknowledge
                                        </button>
                                        <button type="button"
                                                wire:click="openRespond({{ $notice['id'] }}, 'declined')"
                                                class="nks-btn nks-btn--danger nks-btn--sm">
                                            <i class="fa-solid fa-xmark"></i>
                                            Decline
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($showRespond)
        <div class="nks-modal-backdrop" wire:click.self="closeRespond">
            <div class="nks-modal nks-modal--sm">
                <header class="nks-modal-head">
                    <h3>{{ $respondStatus === 'acknowledged' ? 'Acknowledge' : 'Decline' }} notice</h3>
                    <button type="button" wire:click="closeRespond" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Note to employee (optional)</label>
                        <textarea wire:model="respondReason" class="nks-textarea" maxlength="1000"></textarea>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeRespond" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitRespond"
                            class="nks-btn {{ $respondStatus === 'acknowledged' ? 'nks-btn--primary' : 'nks-btn--danger' }}">
                        Confirm
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>