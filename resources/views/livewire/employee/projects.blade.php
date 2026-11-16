<div class="page-projects" wire:init="load">
    <x-nks.loading-overlay :loading="$loading" targets="load" />
    <x-nks.notice-stack :error="$error" :success="$flash" />

    @if ($loading)
        <div class="panel" style="min-height: 400px;"></div>
    @else
        <div class="pj-toolbar">
            <nav class="nks-tabs">
                <button type="button" wire:click="switchTab('projects')"
                        class="nks-tab {{ $activeTab === 'projects' ? 'is-active' : '' }}">
                    My Projects ({{ count($projects) }})
                </button>
                <button type="button" wire:click="switchTab('requests')"
                        class="nks-tab {{ $activeTab === 'requests' ? 'is-active' : '' }}">
                    My Requests ({{ $this->pendingRequestCount }})
                </button>
            </nav>

            <button type="button" wire:click="openRequest('additional')" class="nks-btn nks-btn--outline nks-btn--sm">
                <i class="fa-solid fa-plus"></i>
                Request additional project
            </button>
        </div>

        @if ($activeTab === 'projects')
            @if (count($projects) === 0)
                <div class="panel">
                    <div class="nks-card-empty">
                        <i class="fa-solid fa-diagram-project"></i>
                        <p>You are not assigned to any projects yet.</p>
                        <button type="button" wire:click="openRequest('additional')" class="nks-btn nks-btn--outline nks-btn--sm">
                            <i class="fa-solid fa-plus"></i>
                            Request a project
                        </button>
                    </div>
                </div>
            @else
                <div class="pj-grid">
                    @foreach ($projects as $project)
                        @php
                            $daysLeft = $this->daysUntilEnd($project);
                            $isEnded = $daysLeft !== null && $daysLeft < 0;
                            $engagement = $project['my_engagement'] ?? null;
                            $team = $project['team'] ?? ['count' => 1, 'teammates' => []];
                        @endphp

                        <article class="pj-card pj-card--enhanced">
                            <header class="pj-card-head">
                                <div class="pj-card-icon">
                                    <i class="fa-solid fa-diagram-project"></i>
                                </div>
                                <div class="pj-card-title">
                                    <h3>{{ $project['name'] ?? '-' }}</h3>
                                    <span>{{ $project['project_code'] ?? '' }}</span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                                    @if (! empty($project['is_primary']))
                                        <x-nks.badge tone="gold">Primary</x-nks.badge>
                                    @endif
                                    <x-nks.badge :tone="self::statusTone($project['status'] ?? null)">
                                        {{ self::statusLabel($project['status'] ?? null) }}
                                    </x-nks.badge>
                                </div>
                            </header>

                            @if (! empty($project['description']))
                                <p class="pj-card-description">{{ \Illuminate\Support\Str::limit($project['description'], 140) }}</p>
                            @endif

                            @if (! empty($project['client']))
                                <div class="pj-card-section">
                                    <div class="pj-card-section-title">
                                        <i class="fa-solid fa-building" aria-hidden="true"></i>
                                        Client
                                    </div>
                                    <div class="pj-client">
                                        <strong>{{ $project['client']['company_name'] ?? '-' }}</strong>
                                        @if (! empty($project['client']['primary_contact_name']))
                                            <span>
                                                {{ $project['client']['primary_contact_name'] }}
                                                @if (! empty($project['client']['primary_contact_email']))
                                                    &middot; {{ $project['client']['primary_contact_email'] }}
                                                @endif
                                            </span>
                                        @endif
                                        @if (! empty($project['client']['primary_contact_phone']))
                                            <span>{{ $project['client']['primary_contact_phone'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if (! empty($project['project_manager']))
                                <div class="pj-card-section">
                                    <div class="pj-card-section-title">
                                        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                                        Project Manager
                                    </div>
                                    <div class="pj-pm">
                                        <div class="pj-pm-avatar">
                                            {{ strtoupper(substr($project['project_manager']['name'] ?? 'PM', 0, 1)) }}
                                        </div>
                                        <div class="pj-pm-body">
                                            <strong>{{ $project['project_manager']['name'] ?? '-' }}</strong>
                                            <span>
                                                {{ $project['project_manager']['position'] ?? 'Project Manager' }}
                                                @if (! empty($project['project_manager']['email']))
                                                    &middot; {{ $project['project_manager']['email'] }}
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="pj-card-section">
                                <div class="pj-card-section-title">
                                    <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
                                    My engagement
                                </div>
                                @if ($engagement)
                                    <dl class="pj-engagement">
                                        <div>
                                            <dt>Role</dt>
                                            <dd>{{ ucfirst(str_replace('_', ' ', $engagement['role'] ?? 'team_member')) }}</dd>
                                        </div>
                                        <div>
                                            <dt>Allocation</dt>
                                            <dd>{{ (int) ($engagement['allocation'] ?? 0) }}%</dd>
                                        </div>
                                        <div>
                                            <dt>Since</dt>
                                            <dd>{{ ! empty($engagement['assigned_date']) ? \Carbon\Carbon::parse($engagement['assigned_date'])->format('d M Y') : '-' }}</dd>
                                        </div>
                                        <div>
                                            <dt>Ends</dt>
                                            <dd>{{ ! empty($engagement['end_date']) ? \Carbon\Carbon::parse($engagement['end_date'])->format('d M Y') : 'Ongoing' }}</dd>
                                        </div>
                                    </dl>
                                @else
                                    <p class="pj-muted">Assigned as primary project.</p>
                                @endif
                            </div>

                            <div class="pj-card-section">
                                <div class="pj-card-section-title">
                                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                                    Team ({{ $team['count'] }})
                                </div>
                                <div class="pj-team">
                                    <div class="pj-team-stack">
                                        @foreach (array_slice($team['teammates'], 0, 4) as $mate)
                                            <span class="pj-team-avatar" title="{{ $mate['name'] ?? '' }}">
                                                {{ strtoupper(substr($mate['name'] ?? '?', 0, 1)) }}
                                            </span>
                                        @endforeach
                                        @if (count($team['teammates']) > 4)
                                            <span class="pj-team-avatar pj-team-avatar--more">
                                                +{{ count($team['teammates']) - 4 }}
                                            </span>
                                        @endif
                                    </div>
                                    @if (count($team['teammates']) > 0)
                                        <div class="pj-team-list">
                                            @foreach (array_slice($team['teammates'], 0, 3) as $mate)
                                                <span>{{ $mate['name'] }} ({{ ucfirst(str_replace('_', ' ', $mate['role'] ?? 'team_member')) }})</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if (! empty($project['start_date']) || ! empty($project['end_date']))
                                <div class="pj-card-section">
                                    <div class="pj-card-section-title">
                                        <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                                        Timeline
                                    </div>
                                    <div class="pj-timeline">
                                        <div><span>Started</span><strong>{{ ! empty($project['start_date']) ? \Carbon\Carbon::parse($project['start_date'])->format('d M Y') : '-' }}</strong></div>
                                        <div><span>Ends</span><strong class="{{ $isEnded ? 'is-ended' : '' }}">{{ ! empty($project['end_date']) ? \Carbon\Carbon::parse($project['end_date'])->format('d M Y') : 'Ongoing' }}</strong></div>
                                        @if ($daysLeft !== null)
                                            <div><span>Status</span><strong class="{{ $isEnded ? 'is-ended' : '' }}">{{ $isEnded ? abs($daysLeft) . 'd past end' : $daysLeft . 'd remaining' }}</strong></div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div class="pj-card-actions">
                                <button type="button" wire:click="openRequest('swap', {{ $project['id'] }})" class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                    Request swap
                                </button>
                                <button type="button" wire:click="openRequest('change', {{ $project['id'] }})" class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-sliders"></i>
                                    Request change
                                </button>
                                <button type="button" wire:click="openRequest('concern', {{ $project['id'] }})" class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    Report concern
                                </button>
                                <button type="button" wire:click="openRequest('meeting', {{ $project['id'] }})" class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-handshake"></i>
                                    Request meeting
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        @else
            <div class="panel">
                <div class="page-toolbar">
                    <div>
                        <h2>My Requests</h2>
                        <p>Track the status of every project change request you have raised.</p>
                    </div>
                </div>

                @if (count($requests) === 0)
                    <div class="nks-card-empty">
                        <i class="fa-solid fa-inbox"></i>
                        <p>You have not submitted any project requests yet.</p>
                    </div>
                @else
                    <div class="nks-table-wrapper">
                        <table class="nks-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Project</th>
                                    <th>Target</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Reviewer</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requests as $row)
                                    <tr>
                                        <td>
                                            <x-nks.badge tone="muted">{{ ucfirst($row['type']) }}</x-nks.badge>
                                        </td>
                                        <td>{{ $row['current_project']['name'] ?? '-' }}</td>
                                        <td>{{ $row['target_project']['name'] ?? '-' }}</td>
                                        <td>
                                            <x-nks.badge tone="{{ $row['priority'] === 'urgent' ? 'red' : ($row['priority'] === 'high' ? 'gold' : 'muted') }}">
                                                {{ ucfirst($row['priority'] ?? 'medium') }}
                                            </x-nks.badge>
                                        </td>
                                        <td>
                                            <x-nks.badge :tone="self::requestStatusTone($row['status'] ?? null)">
                                                {{ ucfirst($row['status'] ?? 'pending') }}
                                            </x-nks.badge>
                                        </td>
                                        <td>
                                            {{ isset($row['created_at']) ? \Carbon\Carbon::parse($row['created_at'])->format('d M Y') : '-' }}
                                        </td>
                                        <td>
                                            @if (! empty($row['reviewer']))
                                                {{ $row['reviewer']['first_name'] ?? '' }} {{ $row['reviewer']['last_name'] ?? '' }}
                                            @else
                                                <span class="pj-muted">Awaiting</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    @endif

    @if ($showRequest)
        <div class="nks-modal-backdrop" wire:click.self="closeRequest" @keydown.escape.window="$wire.closeRequest()">
            <div class="nks-modal nks-modal--md" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>{{ $this->requestTypeLabel }}</h3>
                    <button type="button" wire:click="closeRequest" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>

                <div class="nks-modal-body">
                    @if ($requestCurrentProjectName)
                        <div class="nks-notice" style="background: var(--nks-elevated);">
                            <i class="fa-solid fa-circle-info" style="color: var(--nks-gold-deep);"></i>
                            <div class="nks-notice-body">
                                For project <strong>{{ $requestCurrentProjectName }}</strong>
                            </div>
                        </div>
                    @endif

                    @if (in_array($requestType, ['swap', 'additional'], true))
                        <div class="nks-field">
                            <label class="nks-label">
                                {{ $requestType === 'swap' ? 'Move to project' : 'Project to join' }}
                                @if ($requestType === 'swap') <span class="nks-required">*</span> @endif
                            </label>
                            <select wire:model="requestForm.target_project_id" class="nks-select">
                                <option value="">Select a project</option>
                                @foreach ($availableProjects as $p)
                                    <option value="{{ $p['id'] }}">{{ $p['name'] }} ({{ $p['project_code'] ?? '' }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($requestType === 'change')
                        <div class="nks-field">
                            <label class="nks-label">Requested role</label>
                            <select wire:model="requestForm.requested_role" class="nks-select">
                                <option value="">No preference</option>
                                <option value="team_member">Team member</option>
                                <option value="lead">Lead</option>
                                <option value="senior">Senior</option>
                                <option value="junior">Junior</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Requested allocation %</label>
                            <input type="number" min="1" max="100" wire:model="requestForm.requested_allocation" class="nks-input">
                        </div>
                    @endif

                    <div class="nks-field">
                        <label class="nks-label">Priority</label>
                        <select wire:model="requestForm.priority" class="nks-select">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Reason <span class="nks-required">*</span>
                        </label>
                        <textarea wire:model="requestForm.reason"
                                  class="nks-textarea"
                                  maxlength="2000"
                                  placeholder="At least 20 characters. Your project manager will see this."></textarea>
                        <span class="nks-help">{{ strlen($requestForm['reason'] ?? '') }} / 2000</span>
                    </div>
                </div>

                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeRequest" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitRequest" class="nks-btn nks-btn--primary"
                            wire:loading.attr="disabled" wire:target="submitRequest">
                        <i class="fa-solid fa-paper-plane" wire:loading.remove wire:target="submitRequest"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="submitRequest"></span>
                        Submit request
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>