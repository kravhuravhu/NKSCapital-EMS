<div class="page-profile">

    @if ($error)
        <div class="pf-flash pf-flash--error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    @if ($flash)
        <div class="pf-flash pf-flash--success">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ $flash }}</span>
        </div>
    @endif

    <div class="pf-layout">

        <div class="pf-left">
            {{-- tabs --}}
            <nav class="nks-tabs pf-tabs" aria-label="Profile views">
                <a href="{{ route('portal.' . auth()->user()->role . '.profile') }}" class="nks-tab is-active">
                    Profile
                </a>
                <a href="{{ route('portal.' . auth()->user()->role . '.security') }}" class="nks-tab">
                    Security
                </a>
            </nav>

            @if ($loading)
                <x-nks.spinner block label="Loading profile…" />
            @elseif ($profile)
                {{-- profile panel --}}
                <div class="panel pf-panel">

                    {{-- overview --}}
                    <div class="pf-overview">
                        <div class="pf-photo">
                            <div class="pf-avatar">
                                @if ($profile['profile_photo_url'] ?? null)
                                    <img src="{{ $profile['profile_photo_url'] }}" alt="">
                                @else
                                    <i class="fa-solid fa-user"></i>
                                @endif
                            </div>

                            <label class="nks-btn nks-btn--outline nks-btn--sm pf-photo-btn">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                Change Photo
                                <input type="file" wire:model="photo" accept="image/*" hidden>
                            </label>
                            @if ($photo)
                                <button type="button" wire:click="uploadPhoto" class="nks-btn nks-btn--primary nks-btn--sm" style="margin-top:6px;">
                                    Save Photo
                                </button>
                            @endif
                            <span class="pf-photo-hint">JPG, PNG up to 10MB</span>
                        </div>

                        <dl class="pf-details">
                            <div><dt>Full Name</dt><dd>{{ $profile['full_name'] ?? '—' }}</dd></div>
                            <div><dt>Employee ID</dt><dd>{{ $profile['employee_number'] ?? '—' }}</dd></div>
                            <div><dt>Job Title</dt><dd>{{ $profile['position'] ?? '—' }}</dd></div>
                            <div><dt>Department</dt><dd>{{ $profile['department'] ?? '—' }}</dd></div>
                            <div><dt>Work Email</dt><dd>{{ $profile['email'] ?? '—' }}</dd></div>
                            <div><dt>Phone Number</dt><dd>{{ $profile['phone'] ?? '—' }}</dd></div>
                            <div><dt>Reports to</dt><dd>{{ $profile['manager'] ?? '—' }}</dd></div>
                        </dl>
                    </div>

                    {{-- personal details --}}
                    <div class="pf-personal-head">
                        <h2>Personal Details</h2>
                    </div>

                    <div class="pf-personal-grid">
                        <div>
                            <div class="pf-detail-label">Date of Birth</div>
                            <div class="pf-detail-value">—</div>
                        </div>
                        <div>
                            <div class="pf-detail-label">ID Number</div>
                            <div class="pf-detail-value">{{ $profile['id_number'] ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="pf-detail-label">Home Address</div>
                            <div class="pf-detail-value">{{ $profile['address'] ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="pf-detail-label">Emergency Contact</div>
                            <div class="pf-detail-value">
                                {{ $profile['emergency_contact_name'] ?? '—' }}
                                @if ($profile['emergency_contact_phone'] ?? null)
                                    · {{ $profile['emergency_contact_phone'] }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- notice --}}
                <div class="pf-notice">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>If any of the information is incorrect, please contact your manager or HR department.</span>
                </div>
            @endif
        </div>

        <div class="pf-right">
            <div class="panel">
                <h2>Employment Information</h2>

                @foreach ($employment as $row)
                    <div class="pf-info-row">
                        <div class="pf-info-icon">
                            <i class="fa-solid fa-briefcase"></i>
                        </div>
                        <span class="pf-info-label">{{ $row[0] }}</span>
                        <strong class="pf-info-value">{{ $row[1] }}</strong>
                    </div>
                @endforeach
            </div>

            <div class="panel">
                <h2>Contract Information</h2>
                <p class="pf-contract-note">Contract details are managed by your organization. You can only view this information.</p>

                @foreach ($contract as $row)
                    <div class="pf-info-row">
                        <span class="pf-info-label">{{ $row[0] }}</span>
                        <strong class="pf-info-value">{{ $row[1] }}</strong>
                    </div>
                @endforeach
            </div>

            <a href="{{ route('portal.' . auth()->user()->role . '.security') }}" class="pf-account-link">
                <div class="pf-account-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div class="pf-account-text">
                    <div class="pf-account-title">Account Settings</div>
                    <div class="pf-account-desc">Password, 2FA, active sessions</div>
                </div>
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
            </a>
        </div>
    </div>
</div>