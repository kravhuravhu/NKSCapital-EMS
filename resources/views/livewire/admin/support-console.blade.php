<div class="page-support-console" wire:init="load">
    <x-nks.loading-overlay targets="search,selectUser" />
    <x-nks.notice-stack :error="$error" :success="$flash" />

    <div class="panel">
        <div class="page-toolbar">
            <div>
                <h2>Support Console</h2>
                <p>Search users, manage sessions, reset 2FA, and impersonate with audit.</p>
            </div>
        </div>

        <form wire:submit="search" class="sc-search-form">
            <input type="text"
                   wire:model="query"
                   class="nks-input"
                   placeholder="Search by name, email, or employee number"
                   autofocus>
            <button type="submit" class="nks-btn nks-btn--primary" wire:loading.attr="disabled" wire:target="search">
                <i class="fa-solid fa-magnifying-glass" wire:loading.remove wire:target="search"></i>
                <span class="nks-btn-spinner" wire:loading wire:target="search"></span>
                Search
            </button>
        </form>
    </div>

    @if ($selectedUser)
        <div class="panel">
            <div class="page-toolbar">
                <button type="button" wire:click="back" class="nks-btn nks-btn--ghost nks-btn--sm">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to results
                </button>
            </div>

            <div class="sc-user-header">
                <div class="sc-user-avatar">
                    {{ strtoupper(substr($selectedUser['first_name'] ?? 'U', 0, 1) . substr($selectedUser['last_name'] ?? '', 0, 1)) }}
                </div>
                <div class="sc-user-body">
                    <h3>{{ $selectedUser['first_name'] }} {{ $selectedUser['last_name'] }}</h3>
                    <p>
                        {{ $selectedUser['email'] }}
                        &middot; {{ $selectedUser['employee_number'] ?? 'no employee number' }}
                        &middot; {{ ucfirst(str_replace('_', ' ', $selectedUser['role'])) }}
                    </p>
                </div>
                <div class="sc-user-badges">
                    @if ($selectedUser['is_active'])
                        <x-nks.badge tone="success">Active</x-nks.badge>
                    @else
                        <x-nks.badge tone="muted">Inactive</x-nks.badge>
                    @endif
                    @if ($selectedUser['two_factor_enabled'])
                        <x-nks.badge tone="success">2FA on</x-nks.badge>
                    @else
                        <x-nks.badge tone="muted">No 2FA</x-nks.badge>
                    @endif
                </div>
            </div>

            <div class="sc-actions">
                <button type="button" wire:click="openImpersonate" class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-user-secret"></i>
                    Impersonate
                </button>
                <button type="button" wire:click="openForceLogout" class="nks-btn nks-btn--danger nks-btn--sm">
                    <i class="fa-solid fa-power-off"></i>
                    Force logout
                </button>
                <button type="button" wire:click="openReset2fa" class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-shield-halved"></i>
                    Reset 2FA
                </button>
                <button type="button" wire:click="openEmployeeNumber" class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-hashtag"></i>
                    Correct number
                </button>
                <button type="button" wire:click="openRelink" class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-link"></i>
                    Re-link
                </button>
            </div>

            <div class="sc-grid">
                <div class="nks-card" style="padding: 14px 16px;">
                    <h4 style="font-size: 12px; font-weight: 700; margin: 0 0 8px;">Sessions ({{ count($sessions) }})</h4>
                    @if (count($sessions) === 0)
                        <p style="font-size: 12px; color: var(--nks-muted); margin: 0;">No active sessions.</p>
                    @else
                        <table class="nks-table" style="font-size: 11px;">
                            <thead>
                                <tr>
                                    <th>Device</th>
                                    <th>Last used</th>
                                    <th>Expires</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sessions as $s)
                                    <tr>
                                        <td style="font-family: monospace;">{{ \Illuminate\Support\Str::limit($s['name'], 22) }}</td>
                                        <td>{{ $s['last_used_at'] ? \Carbon\Carbon::parse($s['last_used_at'])->diffForHumans() : 'never' }}</td>
                                        <td>{{ $s['expires_at'] ? \Carbon\Carbon::parse($s['expires_at'])->format('d M Y') : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="nks-card" style="padding: 14px 16px;">
                    <h4 style="font-size: 12px; font-weight: 700; margin: 0 0 8px;">Recent audit (last 10)</h4>
                    @if (count($auditSlice) === 0)
                        <p style="font-size: 12px; color: var(--nks-muted); margin: 0;">No audit entries.</p>
                    @else
                        <table class="nks-table" style="font-size: 11px;">
                            <thead>
                                <tr><th>Action</th><th>Time</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($auditSlice as $log)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Str::limit($log['action'] ?? '', 40) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($log['timestamp'])->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    @elseif ($hasSearched)
        <div class="panel">
            @if (count($results) === 0)
                <div class="nks-card-empty">
                    <i class="fa-solid fa-user-slash"></i>
                    <p>No users match "{{ $query }}".</p>
                </div>
            @else
                <div class="nks-table-wrapper">
                    <table class="nks-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Last login</th>
                                <th style="width: 100px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $u)
                                <tr wire:key="sc-{{ $u['id'] }}">
                                    <td>
                                        <div style="font-weight: 600;">{{ $u['first_name'] }} {{ $u['last_name'] }}</div>
                                        <div style="font-size: 11px; color: var(--nks-muted);">
                                            {{ $u['email'] }} &middot; {{ $u['employee_number'] ?? '-' }}
                                        </div>
                                    </td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $u['role'])) }}</td>
                                    <td>{{ $u['department'] ?? '-' }}</td>
                                    <td>
                                        {{ $u['last_login_at'] ? \Carbon\Carbon::parse($u['last_login_at'])->diffForHumans() : 'never' }}
                                    </td>
                                    <td>
                                        <button type="button" wire:click="selectUser({{ $u['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            Open
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- Impersonate modal --}}
    @if ($showImpersonate)
        <div class="nks-modal-backdrop">
            <div class="nks-modal nks-modal--sm">
                <header class="nks-modal-head">
                    <h3>Impersonate {{ $selectedUser['first_name'] ?? '' }}</h3>
                    <button type="button" wire:click="closeImpersonate" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <p style="font-size: 12px; margin: 0 0 12px;">
                        You will operate as this user. Every action is logged with your admin ID as the impersonator.
                        A banner appears on every page. Click <strong>Stop impersonating</strong> when done.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Your admin password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="impersonatePassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeImpersonate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="confirmImpersonate"
                            wire:loading.attr="disabled" wire:target="confirmImpersonate"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-user-secret" wire:loading.remove wire:target="confirmImpersonate"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="confirmImpersonate"></span>
                        Impersonate
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Force logout modal --}}
    @if ($showForceLogout)
        <div class="nks-modal-backdrop">
            <div class="nks-modal nks-modal--sm">
                <header class="nks-modal-head">
                    <h3>Force logout {{ $selectedUser['first_name'] ?? '' }}</h3>
                    <button type="button" wire:click="closeForceLogout" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <p style="font-size: 12px; margin: 0 0 12px;">All active sessions for this user will be terminated. They will need to sign in again.</p>
                    <div class="nks-field">
                        <label class="nks-label">Your admin password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="forceLogoutPassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeForceLogout" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="confirmForceLogout"
                            wire:loading.attr="disabled" wire:target="confirmForceLogout"
                            class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-power-off" wire:loading.remove wire:target="confirmForceLogout"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="confirmForceLogout"></span>
                        Force logout
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Reset 2FA modal --}}
    @if ($showReset2fa)
        <div class="nks-modal-backdrop">
            <div class="nks-modal nks-modal--sm">
                <header class="nks-modal-head">
                    <h3>Reset 2FA</h3>
                    <button type="button" wire:click="closeReset2fa" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <p style="font-size: 12px; margin: 0 0 12px;">Clears the user's TOTP secret and recovery codes. They will be prompted to enroll a new device.</p>
                    <div class="nks-field">
                        <label class="nks-label">Your admin password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="reset2faPassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeReset2fa" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="confirmReset2fa"
                            wire:loading.attr="disabled" wire:target="confirmReset2fa"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-shield-halved" wire:loading.remove wire:target="confirmReset2fa"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="confirmReset2fa"></span>
                        Reset 2FA
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Employee number modal --}}
    @if ($showEmployeeNumber)
        <div class="nks-modal-backdrop">
            <div class="nks-modal nks-modal--sm">
                <header class="nks-modal-head">
                    <h3>Correct employee number</h3>
                    <button type="button" wire:click="closeEmployeeNumber" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">New employee number</label>
                        <input type="text" wire:model="newEmployeeNumber" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Your admin password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="employeeNumberPassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeEmployeeNumber" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="confirmEmployeeNumber"
                            wire:loading.attr="disabled" wire:target="confirmEmployeeNumber"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check" wire:loading.remove wire:target="confirmEmployeeNumber"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="confirmEmployeeNumber"></span>
                        Save
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Relink modal --}}
    @if ($showRelink)
        <div class="nks-modal-backdrop">
            <div class="nks-modal">
                <header class="nks-modal-head">
                    <h3>Re-link relationships</h3>
                    <button type="button" wire:click="closeRelink" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Manager</label>
                        <select wire:model="relinkForm.manager_id" class="nks-select">
                            <option value="">No manager</option>
                            @foreach ($managers as $m)
                                <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Client</label>
                        <select wire:model="relinkForm.client_id" class="nks-select">
                            <option value="">No client</option>
                            @foreach ($clients as $c)
                                <option value="{{ $c['id'] }}">{{ $c['company_name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Project</label>
                        <select wire:model="relinkForm.project_id" class="nks-select">
                            <option value="">No project</option>
                            @foreach ($projects as $p)
                                <option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Your admin password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="relinkPassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeRelink" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="confirmRelink"
                            wire:loading.attr="disabled" wire:target="confirmRelink"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check" wire:loading.remove wire:target="confirmRelink"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="confirmRelink"></span>
                        Save
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>