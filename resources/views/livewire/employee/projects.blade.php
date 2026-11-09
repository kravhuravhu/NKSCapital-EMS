<div class="page-projects">
    @if ($error)
        <div class="nks-notice" style="background: rgba(194, 91, 91, 0.1); color: var(--nks-danger);">
            <i class="fa-solid fa-circle-exclamation" style="color: var(--nks-danger);"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($loading)
        <x-nks.spinner block label="Loading projects..." />
    @else
        @if ($primary)
            <div class="panel pj-primary-panel">
                <div class="pj-primary-row">
                    <div class="pj-primary-icon"><i class="fa-solid fa-star"></i></div>
                    <div class="pj-primary-body">
                        <span class="pj-primary-label">Primary project</span>
                        <strong>{{ $primary['name'] ?? '—' }}</strong>
                        <span class="pj-primary-code">{{ $primary['project_code'] ?? '' }}</span>
                    </div>
                    <x-nks.badge :tone="self::statusTone($primary['status'] ?? null)">
                        {{ self::statusLabel($primary['status'] ?? null) }}
                    </x-nks.badge>
                </div>
            </div>
        @endif

        @if (count($projects) === 0)
            <div class="panel">
                <div class="nks-card-empty">
                    <i class="fa-solid fa-diagram-project"></i>
                    <p>You have not been assigned to any projects yet.</p>
                </div>
            </div>
        @else
            <div class="pj-grid">
                @foreach ($projects as $assignment)
                    @php
                        $p = $assignment['project'] ?? [];
                        $allocation = (float) ($assignment['allocation'] ?? 0);
                        $daysLeft = $this->daysUntilEnd($p);
                        $isEnded = $daysLeft !== null && $daysLeft < 0;
                    @endphp
                    <article class="pj-card">
                        <header class="pj-card-head">
                            <div class="pj-card-icon"><i class="fa-solid fa-diagram-project"></i></div>
                            <div class="pj-card-title">
                                <h3>{{ $p['name'] ?? '—' }}</h3>
                                <span>{{ $p['project_code'] ?? '' }}</span>
                            </div>
                            <x-nks.badge :tone="self::statusTone($p['status'] ?? null)">
                                {{ self::statusLabel($p['status'] ?? null) }}
                            </x-nks.badge>
                        </header>

                        <dl class="pj-card-meta">
                            <div>
                                <dt>My role</dt>
                                <dd>{{ ucfirst(str_replace('_', ' ', $assignment['role'] ?? 'team_member')) }}</dd>
                            </div>
                            <div>
                                <dt>Allocation</dt>
                                <dd>{{ (int) $allocation }}%</dd>
                            </div>
                            <div>
                                <dt>Since</dt>
                                <dd>
                                    {{ ! empty($assignment['assigned_date']) ? \Carbon\Carbon::parse($assignment['assigned_date'])->format('d M Y') : '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt>Project manager</dt>
                                <dd>
                                    @if (! empty($p['project_manager']))
                                        {{ $p['project_manager']['first_name'] ?? '' }}
                                        {{ $p['project_manager']['last_name'] ?? '' }}
                                    @else
                                        Unassigned
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        @if ($this->canSeeFinancials() && ! empty($p['budgeted_hours']))
                            <div class="pj-card-budget">
                                <span>Budgeted hours</span>
                                <strong>{{ \App\Livewire\Employee\Timesheet::formatHours((float) $p['budgeted_hours']) }}</strong>
                            </div>
                        @endif

                        @if (! empty($p['end_date']))
                            <div class="pj-card-footer">
                                <span class="pj-card-deadline {{ $isEnded ? 'is-ended' : '' }}">
                                    <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                                    {{ \Carbon\Carbon::parse($p['end_date'])->format('d M Y') }}
                                    @if ($daysLeft !== null)
                                        &middot;
                                        {{ $isEnded
                                            ? 'ended ' . abs($daysLeft) . 'd ago'
                                            : $daysLeft . 'd remaining' }}
                                    @endif
                                </span>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    @endif
</div>