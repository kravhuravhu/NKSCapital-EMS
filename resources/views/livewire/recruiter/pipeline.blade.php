<div class="page-pipeline">
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

    <div class="panel pl-panel">
        <div class="pl-header">
            <i class="fa-solid fa-columns pl-header-icon"></i>
            <div>
                <h2>Candidate Pipeline</h2>
                <p>
                    Drag candidates between stages, or use the arrow buttons on each card.
                    @if (!empty($total))
                        Total candidates: {{ $total }}
                    @endif
                </p>
            </div>
            <div class="pl-header-actions">
                <div class="pl-filter-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text"
                           wire:model.live.debounce.400ms="search"
                           wire:keydown.enter="applyFilters"
                           class="pl-search-input"
                           placeholder="Search name or email">
                </div>
                <select wire:model.live="requisitionFilter" class="pl-select">
                    <option value="">All requisitions</option>
                    @foreach ($requisitions as $r)
                        <option value="{{ $r['id'] }}">
                            {{ $r['requisition_code'] ?? '' }} — {{ $r['title'] ?? '' }}
                        </option>
                    @endforeach
                </select>
                <button type="button" wire:click="refreshBoard" class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-rotate"></i>
                    Refresh
                </button>
            </div>
        </div>

        @if ($truncated)
            <div class="pl-truncated">
                <i class="fa-solid fa-circle-info"></i>
                <span>
                    Showing the first 200 candidates. Narrow down with the requisition filter or search to see the rest.
                </span>
            </div>
        @endif

        @if ($loading)
            <x-nks.spinner block label="Loading pipeline…" />
        @else
            <div class="pl-board">
                @foreach ($stageOrder as $stage)
                    @php
                        $rows = $columns[$stage] ?? [];
                        $count = count($rows);
                    @endphp
                    <div class="pl-column"
                         x-data="{ dragover: false }"
                         @dragover.prevent="dragover = true"
                         @dragleave="dragover = false"
                         @drop.prevent="dragover = false; $wire.moveCard($event.dataTransfer.getData('text/plain'), '{{ $stage }}')"
                         :class="dragover ? 'is-dragover' : ''">
                        <div class="pl-column-head pl-column-head--{{ \App\Livewire\Recruiter\Pipeline::stageTone($stage) }}">
                            <div class="pl-column-title">
                                {{ $stageLabels[$stage] }}
                            </div>
                            <span class="pl-column-count">{{ $count }}</span>
                        </div>

                        <div class="pl-column-body">
                            @if ($count === 0)
                                <div class="pl-column-empty">
                                    <i class="fa-solid fa-inbox"></i>
                                    <span>No candidates</span>
                                </div>
                            @else
                                @foreach ($rows as $c)
                                    @php
                                        $canMove = $this->canMoveOut($c);
                                    @endphp
                                    <div class="pl-card"
                                         wire:key="pl-card-{{ $c['id'] }}"
                                         draggable="{{ $canMove ? 'true' : 'false' }}"
                                         @dragstart="$event.dataTransfer.setData('text/plain', '{{ $c['id'] }}'); $event.dataTransfer.effectAllowed = 'move';"
                                         @click="$wire.openView({{ $c['id'] }})">
                                        <div class="pl-card-head">
                                            <div class="pl-card-name">
                                                {{ $c['first_name'] ?? '' }} {{ $c['last_name'] ?? '' }}
                                            </div>
                                            <span class="pl-card-age">
                                                {{ \App\Livewire\Recruiter\Pipeline::ageLabel($c['applied_date'] ?? null) }}
                                            </span>
                                        </div>
                                        <div class="pl-card-meta">{{ $c['email'] ?? '' }}</div>
                                        @if (!empty($c['requisition']))
                                            <div class="pl-card-req">
                                                <i class="fa-solid fa-briefcase"></i>
                                                {{ $c['requisition']['requisition_code'] ?? '' }}
                                            </div>
                                        @endif
                                        @if (!empty($c['skills']) && is_array($c['skills']))
                                            <div class="pl-card-skills">
                                                @foreach (array_slice($c['skills'], 0, 2) as $skill)
                                                    <span class="pl-skill-chip">{{ $skill }}</span>
                                                @endforeach
                                                @if (count($c['skills']) > 2)
                                                    <span class="pl-skill-chip pl-skill-chip--more">+{{ count($c['skills']) - 2 }}</span>
                                                @endif
                                            </div>
                                        @endif

                                        @if ($canMove)
                                            <div class="pl-card-actions" @click.stop>
                                                <button type="button"
                                                        wire:click="regressCard({{ $c['id'] }})"
                                                        class="pl-arrow"
                                                        title="Move back"
                                                        @if (!$this->canRegressInLinear($c)) disabled @endif>
                                                    <i class="fa-solid fa-chevron-left"></i>
                                                </button>
                                                <button type="button"
                                                        wire:click="advanceCard({{ $c['id'] }})"
                                                        class="pl-arrow"
                                                        title="Move forward"
                                                        @if (!$this->canAdvanceInLinear($c)) disabled @endif>
                                                    <i class="fa-solid fa-chevron-right"></i>
                                                </button>
                                                <button type="button"
                                                        wire:click="openReject({{ $c['id'] }})"
                                                        class="pl-reject"
                                                        title="Reject">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- view --}}
    @if ($showView && $viewData)
        @php
            $vStage = $viewData['current_stage'] ?? 'applied';
            $vCanMove = $this->canMoveOut($viewData);
        @endphp
        <div class="pl-modal-backdrop" wire:click.self="closeView">
            <div class="pl-modal pl-modal--lg">
                <header class="pl-modal-head">
                    <div>
                        <h3>{{ $viewData['first_name'] ?? '' }} {{ $viewData['last_name'] ?? '' }}</h3>
                        <span class="pl-modal-sub">{{ $viewData['email'] ?? '' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="pl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="pl-modal-body">
                    <div class="pl-detail-badges">
                        <x-nks.badge :tone="\App\Livewire\Recruiter\Pipeline::stageTone($vStage)">
                            {{ ucfirst(str_replace('_', ' ', $vStage)) }}
                        </x-nks.badge>
                        @if (!empty($viewData['source']))
                            <x-nks.badge tone="muted">Source: {{ ucfirst($viewData['source']) }}</x-nks.badge>
                        @endif
                    </div>

                    <div class="pl-detail-grid">
                        <div>
                            <span class="pl-detail-label">Phone</span>
                            <span class="pl-detail-value">{{ $viewData['phone'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="pl-detail-label">Experience</span>
                            <span class="pl-detail-value">
                                {{ !empty($viewData['years_experience']) ? $viewData['years_experience'] . ' years' : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="pl-detail-label">Expected Salary</span>
                            <span class="pl-detail-value">
                                @if (!empty($viewData['expected_salary']))
                                    R {{ number_format((float) $viewData['expected_salary'], 2) }}
                                @else — @endif
                            </span>
                        </div>
                        <div>
                            <span class="pl-detail-label">Applied</span>
                            <span class="pl-detail-value">
                                {{ !empty($viewData['applied_date']) ? \Carbon\Carbon::parse($viewData['applied_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div class="pl-detail-full">
                            <span class="pl-detail-label">Requisition</span>
                            <span class="pl-detail-value">
                                @if (!empty($viewData['requisition']))
                                    {{ $viewData['requisition']['title'] ?? '—' }}
                                    ({{ $viewData['requisition']['requisition_code'] ?? '' }})
                                @else — @endif
                            </span>
                        </div>
                    </div>

                    @if (!empty($viewData['skills']) && is_array($viewData['skills']))
                        <div class="pl-detail-section">
                            <span class="pl-detail-label">Skills</span>
                            <div class="pl-skill-chips">
                                @foreach ($viewData['skills'] as $skill)
                                    <span class="pl-skill-chip">{{ $skill }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (!empty($viewData['notes']))
                        <div class="pl-detail-section">
                            <span class="pl-detail-label">Notes</span>
                            <p class="pl-detail-text">{{ $viewData['notes'] }}</p>
                        </div>
                    @endif

                    @if (!empty($viewData['rejection_reason']))
                        <div class="pl-detail-section">
                            <span class="pl-detail-label">Rejection Reason</span>
                            <p class="pl-detail-text pl-detail-text--danger">{{ $viewData['rejection_reason'] }}</p>
                        </div>
                    @endif
                </div>
                <footer class="pl-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    @if ($vCanMove)
                        <button type="button" wire:click="openReject({{ $viewData['id'] }})" class="nks-btn nks-btn--danger">
                            <i class="fa-solid fa-xmark"></i> Reject
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif

    {{-- reject --}}
    @if ($showReject)
        <div class="pl-modal-backdrop" wire:click.self="closeReject">
            <div class="pl-modal">
                <header class="pl-modal-head">
                    <h3>Reject Candidate</h3>
                    <button type="button" wire:click="closeReject" class="pl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="pl-modal-body">
                    <p class="pl-text">
                        Reject <strong>{{ $rejectName }}</strong>?
                        The reason is visible to the recruiter team only.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Reason <span class="nks-required">*</span></label>
                        <textarea wire:model="rejectReason" class="nks-textarea"
                                  placeholder="Explain the rejection for the record."></textarea>
                    </div>
                </div>
                <footer class="pl-modal-foot">
                    <button type="button" wire:click="closeReject" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitReject" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-xmark"></i> Reject Candidate
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>