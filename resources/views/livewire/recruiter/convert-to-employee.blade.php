<div class="page-convert">
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

    <div class="panel cv-panel">
        <div class="cv-header">
            <i class="fa-solid fa-user-plus cv-header-icon"></i>
            <div>
                <h2>Convert to Employee</h2>
                <p>Turn an accepted offer into a fully onboarded employee account</p>
            </div>
            @if ($selectedOffer)
                <button type="button" wire:click="backToList" class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to List
                </button>
            @endif
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading…" />
        @elseif ($selectedOffer)
            {{-- single conversion view --}}
            <div class="cv-grid">
                {{-- LEFT — candidate summary --}}
                <div class="cv-summary">
                    <div class="cv-summary-head">
                        <div class="cv-avatar">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div>
                            <div class="cv-summary-name">
                                {{ $candidate['first_name'] ?? '' }} {{ $candidate['last_name'] ?? '' }}
                            </div>
                            <div class="cv-summary-meta">{{ $candidate['email'] ?? '' }}</div>
                        </div>
                    </div>

                    <div class="cv-summary-rows">
                        <div>
                            <span class="cv-summary-label">Position</span>
                            <span class="cv-summary-value">{{ $selectedOffer['position'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="cv-summary-label">Department</span>
                            <span class="cv-summary-value">{{ $selectedOffer['department'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="cv-summary-label">Contract Type</span>
                            <span class="cv-summary-value">{{ ucfirst(str_replace('_', ' ', $selectedOffer['contract_type'] ?? '—')) }}</span>
                        </div>
                        <div>
                            <span class="cv-summary-label">Salary Offered</span>
                            <span class="cv-summary-value">
                                @if (!empty($selectedOffer['salary_offered']))
                                    R {{ number_format((float) $selectedOffer['salary_offered'], 2) }}
                                @else — @endif
                            </span>
                        </div>
                        <div>
                            <span class="cv-summary-label">Start Date</span>
                            <span class="cv-summary-value">
                                {{ !empty($selectedOffer['start_date']) ? \Carbon\Carbon::parse($selectedOffer['start_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="cv-summary-label">Requisition</span>
                            <span class="cv-summary-value">
                                @if (!empty($candidate['requisition']))
                                    {{ $candidate['requisition']['requisition_code'] ?? '—' }}
                                @else — @endif
                            </span>
                        </div>
                        <div class="cv-summary-full">
                            <span class="cv-summary-label">Accepted</span>
                            <span class="cv-summary-value">
                                {{ !empty($selectedOffer['accepted_at']) ? \Carbon\Carbon::parse($selectedOffer['accepted_at'])->diffForHumans() : '—' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- RIGHT — conversion form --}}
                <div class="cv-form">
                    <div class="cv-form-title">Employee Details</div>

                    <div class="cv-form-grid">
                        <div class="nks-field">
                            <label class="nks-label">Role <span class="nks-required">*</span></label>
                            <select wire:model.live="form.role" class="nks-select">
                                <option value="employee">Employee</option>
                                <option value="manager">Manager</option>
                                <option value="director">Director</option>
                                <option value="admin">Admin</option>
                                <option value="recruiter">Recruiter</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Employee Type <span class="nks-required">*</span></label>
                            <select wire:model.live="form.employee_type" class="nks-select">
                                <option value="ps">Professional Services (PS)</option>
                                <option value="pr">Projects (PR)</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Service Type <span class="nks-required">*</span></label>
                            <select wire:model="form.service_type" class="nks-select">
                                <option value="permanent">Permanent</option>
                                <option value="contractor">Contractor</option>
                                <option value="temporary">Temporary</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Manager</label>
                            <select wire:model="form.manager_id" class="nks-select">
                                <option value="">No manager</option>
                                @foreach ($managers as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if ($form['employee_type'] === 'ps')
                            <div class="nks-field cv-form-full">
                                <label class="nks-label">Client <span class="nks-required">*</span></label>
                                <select wire:model="form.client_id" class="nks-select">
                                    <option value="">Select client</option>
                                    @foreach ($clients as $c)
                                        <option value="{{ $c['id'] }}">{{ $c['company_name'] }}</option>
                                    @endforeach
                                </select>
                                <span class="nks-help">PS employees must be assigned to a client.</span>
                            </div>
                        @else
                            <div class="nks-field cv-form-full">
                                <label class="nks-label">Project <span class="nks-required">*</span></label>
                                <select wire:model="form.project_id" class="nks-select">
                                    <option value="">Select project</option>
                                    @foreach ($projects as $p)
                                        <option value="{{ $p['id'] }}">{{ $p['name'] }} ({{ $p['project_code'] }})</option>
                                    @endforeach
                                </select>
                                <span class="nks-help">PR employees must be assigned to a project.</span>
                            </div>
                        @endif

                        <div class="nks-field cv-form-full">
                            <label class="nks-label">Conversion Notes</label>
                            <textarea wire:model="form.notes" class="nks-textarea"
                                      placeholder="Anything the new employee's manager should know about the onboarding."></textarea>
                        </div>
                    </div>

                    <div class="cv-submit-row">
                        <button type="button"
                                wire:click="submit"
                                wire:loading.attr="disabled"
                                wire:target="submit"
                                class="nks-btn nks-btn--primary">
                            <i class="fa-solid fa-user-plus"></i>
                            Convert to Employee
                        </button>
                    </div>

                    <div class="cv-note">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>
                            A temporary password will be generated, sent to the new employee by email, and shown to you once
                            for safekeeping. Leave balances are seeded from the configured default entitlements for
                            annual, sick, and family leave.
                        </span>
                    </div>
                </div>
            </div>
        @else
            {{-- accepted offers list --}}
            @if (count($acceptedOffers) === 0)
                <div class="cv-empty">
                    <i class="fa-solid fa-user-check"></i>
                    <strong>No candidates ready for conversion</strong>
                    <span>
                        Accepted offers appear here once a candidate has accepted. Convert one to onboard them as a new employee.
                    </span>
                </div>
            @else
                <div class="cv-table-wrapper">
                    <table class="cv-table">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Position</th>
                                <th>Salary</th>
                                <th>Start Date</th>
                                <th>Accepted</th>
                                <th style="width: 140px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($acceptedOffers as $offer)
                                <tr wire:key="cv-{{ $offer['id'] }}">
                                    <td>
                                        <div class="cv-name">
                                            {{ $offer['candidate']['first_name'] ?? '' }} {{ $offer['candidate']['last_name'] ?? '' }}
                                        </div>
                                        <div class="cv-meta">{{ $offer['candidate']['email'] ?? '' }}</div>
                                    </td>
                                    <td>{{ $offer['position'] ?? '—' }}</td>
                                    <td>
                                        @if (!empty($offer['salary_offered']))
                                            R {{ number_format((float) $offer['salary_offered'], 2) }}
                                        @else — @endif
                                    </td>
                                    <td>
                                        {{ !empty($offer['start_date']) ? \Carbon\Carbon::parse($offer['start_date'])->format('d M Y') : '—' }}
                                    </td>
                                    <td>
                                        {{ !empty($offer['accepted_at']) ? \Carbon\Carbon::parse($offer['accepted_at'])->diffForHumans() : '—' }}
                                    </td>
                                    <td>
                                        <button type="button"
                                                wire:click="selectOffer({{ $offer['id'] }})"
                                                class="nks-btn nks-btn--primary nks-btn--sm">
                                            <i class="fa-solid fa-user-plus"></i>
                                            Convert
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>

    {{-- credentials modal after conversion --}}
    @if ($showCredentials && $credentials)
        <div class="cv-modal-backdrop">
            <div class="cv-modal">
                <header class="cv-modal-head">
                    <h3>Employee Created Successfully</h3>
                </header>
                <div class="cv-modal-body">
                    <p class="cv-text">
                        <strong>{{ $credentials['name'] ?: 'The new employee' }}</strong> has been onboarded.
                        The credentials below have been emailed to <strong>{{ $credentials['email'] }}</strong>.
                        Copy them now if you want a local record — they cannot be shown again.
                    </p>

                    <div class="cv-cred-meta">
                        <div>
                            <span class="cv-cred-label">Employee Number</span>
                            <span class="cv-cred-value">{{ $credentials['employee_number'] }}</span>
                        </div>
                        <div>
                            <span class="cv-cred-label">Email</span>
                            <span class="cv-cred-value">{{ $credentials['email'] }}</span>
                        </div>
                    </div>

                    @if (!empty($credentials['temporary_password']))
                        <div class="cv-cred-password">
                            <label class="cv-cred-label">Temporary Password</label>
                            <div class="cv-cred-value-box">
                                <code>{{ $credentials['temporary_password'] }}</code>
                            </div>
                            <button type="button"
                                    class="nks-btn nks-btn--outline nks-btn--sm"
                                    onclick="navigator.clipboard.writeText('{{ $credentials['temporary_password'] }}')">
                                <i class="fa-solid fa-copy"></i>
                                Copy Password
                            </button>
                        </div>
                    @else
                        <div class="cv-cred-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>
                                The temporary password was not returned by the API. The employee has received it by email.
                                If they don't receive it, use Reset Password from the employee list.
                            </span>
                        </div>
                    @endif

                    <div class="cv-cred-warning">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>
                            Ask the employee to change their password on first login.
                            The temporary password is valid until they do.
                        </span>
                    </div>
                </div>
                <footer class="cv-modal-foot">
                    <a href="{{ route('portal.admin.employees') }}" wire:navigate class="nks-btn nks-btn--outline">
                        <i class="fa-solid fa-users-cog"></i>
                        View in Employees
                    </a>
                    <button type="button" wire:click="closeCredentials" class="nks-btn nks-btn--primary">
                        Done
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>