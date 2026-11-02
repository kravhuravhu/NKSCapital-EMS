<div class="page-candidates">
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

    <div class="panel cd-panel">
        <div class="cd-header">
            <i class="fa-solid fa-user-plus cd-header-icon"></i>
            <div>
                <h2>Candidates</h2>
                <p>Applicants across every requisition, add new ones, and move them through the pipeline</p>
            </div>
            <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary cd-create-btn">
                <i class="fa-solid fa-plus"></i>
                Add Candidate
            </button>
        </div>

        <nav class="nks-tabs cd-stage-tabs">
            <button type="button"
                    wire:click="selectStage('')"
                    class="nks-tab {{ $stageFilter === '' ? 'is-active' : '' }}">
                All
            </button>
            <button type="button"
                    wire:click="selectStage('applied')"
                    class="nks-tab {{ $stageFilter === 'applied' ? 'is-active' : '' }}">
                Applied
            </button>
            <button type="button"
                    wire:click="selectStage('screening')"
                    class="nks-tab {{ $stageFilter === 'screening' ? 'is-active' : '' }}">
                Screening
            </button>
            <button type="button"
                    wire:click="selectStage('interview')"
                    class="nks-tab {{ $stageFilter === 'interview' ? 'is-active' : '' }}">
                Interview
            </button>
            <button type="button"
                    wire:click="selectStage('offer')"
                    class="nks-tab {{ $stageFilter === 'offer' ? 'is-active' : '' }}">
                Offer
            </button>
            <button type="button"
                    wire:click="selectStage('hired')"
                    class="nks-tab {{ $stageFilter === 'hired' ? 'is-active' : '' }}">
                Hired
            </button>
            <button type="button"
                    wire:click="selectStage('rejected')"
                    class="nks-tab {{ $stageFilter === 'rejected' ? 'is-active' : '' }}">
                Rejected
            </button>
        </nav>

        <div class="cd-filters">
            <div class="nks-field cd-filter-search">
                <label class="nks-label">Search</label>
                <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                       class="nks-input" placeholder="Name, email, or phone">
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
            <div class="nks-field">
                <label class="nks-label">Source</label>
                <select wire:model="sourceFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="manual">Manual</option>
                    <option value="linkedin">LinkedIn</option>
                    <option value="referral">Referral</option>
                    <option value="website">Website</option>
                    <option value="recruiter">Recruiter</option>
                </select>
            </div>
            <div class="cd-filter-actions">
                <button type="button" wire:click="resetFilters" class="nks-btn nks-btn--ghost nks-btn--sm">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </button>
                <button type="button" wire:click="applyFilters" class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-filter"></i> Apply
                </button>
            </div>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading candidates…" />
        @elseif (count($candidates) === 0)
            <div class="cd-empty">
                <i class="fa-solid fa-user-plus"></i>
                <strong>No candidates found</strong>
                <span>Adjust filters or add a new candidate to get started.</span>
            </div>
        @else
            <div class="cd-table-wrapper">
                <table class="cd-table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Requisition</th>
                            <th>Stage</th>
                            <th>Source</th>
                            <th>Exp</th>
                            <th>Applied</th>
                            <th style="width: 240px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($candidates as $c)
                            @php
                                $stage = $c['current_stage'] ?? 'applied';
                                $canAdvance = !in_array($stage, ['hired', 'rejected', 'withdrawn'], true);
                            @endphp
                            <tr wire:key="cd-{{ $c['id'] }}">
                                <td>
                                    <div class="cd-name">
                                        {{ $c['first_name'] ?? '' }} {{ $c['last_name'] ?? '' }}
                                    </div>
                                    <div class="cd-meta">{{ $c['email'] ?? '' }}</div>
                                </td>
                                <td>
                                    @if (!empty($c['requisition']))
                                        <div class="cd-name">{{ $c['requisition']['title'] ?? '—' }}</div>
                                        <div class="cd-meta">{{ $c['requisition']['requisition_code'] ?? '' }}</div>
                                    @else
                                        <span class="cd-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Recruiter\Candidates::stageTone($stage)">
                                        {{ ucfirst(str_replace('_', ' ', $stage)) }}
                                    </x-nks.badge>
                                </td>
                                <td>{{ ucfirst($c['source'] ?? '—') }}</td>
                                <td>
                                    {{ !empty($c['years_experience']) ? $c['years_experience'] . 'y' : '—' }}
                                </td>
                                <td>
                                    {{ !empty($c['applied_date']) ? \Carbon\Carbon::parse($c['applied_date'])->format('d M Y') : '—' }}
                                </td>
                                <td>
                                    <div class="cd-row-actions">
                                        <button type="button" wire:click="openView({{ $c['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>
                                        @if ($canAdvance)
                                            <button type="button" wire:click="openAdvance({{ $c['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-arrow-right"></i> Advance
                                            </button>
                                            <button type="button" wire:click="openReject({{ $c['id'] }})"
                                                    class="nks-btn nks-btn--danger nks-btn--sm">
                                                <i class="fa-solid fa-xmark"></i> Reject
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (($pagination['last_page'] ?? 1) > 1)
                <div class="cd-pagination">
                    <button type="button" wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] <= 1) disabled @endif>
                        <i class="fa-solid fa-chevron-left"></i> Previous
                    </button>
                    <span class="cd-page-info">
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

    {{-- view --}}
    @if ($showView && $viewData)
        @php
            $vStage = $viewData['current_stage'] ?? 'applied';
            $vCanAdvance = !in_array($vStage, ['hired', 'rejected', 'withdrawn'], true);
        @endphp
        <div class="cd-modal-backdrop" wire:click.self="closeView">
            <div class="cd-modal cd-modal--lg">
                <header class="cd-modal-head">
                    <div>
                        <h3>{{ $viewData['first_name'] ?? '' }} {{ $viewData['last_name'] ?? '' }}</h3>
                        <span class="cd-modal-sub">{{ $viewData['email'] ?? '' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="cd-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="cd-modal-body">
                    <div class="cd-detail-badges">
                        <x-nks.badge :tone="\App\Livewire\Recruiter\Candidates::stageTone($vStage)">
                            {{ ucfirst(str_replace('_', ' ', $vStage)) }}
                        </x-nks.badge>
                        @if (!empty($viewData['source']))
                            <x-nks.badge tone="muted">Source: {{ ucfirst($viewData['source']) }}</x-nks.badge>
                        @endif
                    </div>

                    <div class="cd-detail-grid">
                        <div>
                            <span class="cd-detail-label">Phone</span>
                            <span class="cd-detail-value">{{ $viewData['phone'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="cd-detail-label">Years of Experience</span>
                            <span class="cd-detail-value">{{ $viewData['years_experience'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="cd-detail-label">Expected Salary</span>
                            <span class="cd-detail-value">
                                @if (!empty($viewData['expected_salary']))
                                    R {{ number_format((float) $viewData['expected_salary'], 2) }}
                                @else — @endif
                            </span>
                        </div>
                        <div>
                            <span class="cd-detail-label">Applied</span>
                            <span class="cd-detail-value">
                                {{ !empty($viewData['applied_date']) ? \Carbon\Carbon::parse($viewData['applied_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="cd-detail-label">Requisition</span>
                            <span class="cd-detail-value">
                                @if (!empty($viewData['requisition']))
                                    {{ $viewData['requisition']['title'] ?? '—' }}
                                    ({{ $viewData['requisition']['requisition_code'] ?? '' }})
                                @else — @endif
                            </span>
                        </div>
                        <div>
                            <span class="cd-detail-label">Requisition Status</span>
                            <span class="cd-detail-value">
                                {{ !empty($viewData['requisition']['status']) ? ucfirst(str_replace('_', ' ', $viewData['requisition']['status'])) : '—' }}
                            </span>
                        </div>
                        @if (!empty($viewData['linkedin_url']))
                            <div class="cd-detail-full">
                                <span class="cd-detail-label">LinkedIn</span>
                                <a href="{{ $viewData['linkedin_url'] }}" target="_blank" rel="noopener" class="cd-detail-link">
                                    {{ $viewData['linkedin_url'] }}
                                </a>
                            </div>
                        @endif
                    </div>

                    @if (!empty($viewData['skills']))
                        <div class="cd-detail-section">
                            <span class="cd-detail-label">Skills</span>
                            <div class="cd-chip-group">
                                @foreach ($viewData['skills'] as $skill)
                                    <span class="cd-chip">{{ $skill }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (!empty($viewData['notes']))
                        <div class="cd-detail-section">
                            <span class="cd-detail-label">Notes</span>
                            <p class="cd-detail-text">{{ $viewData['notes'] }}</p>
                        </div>
                    @endif

                    @if (!empty($viewData['rejection_reason']))
                        <div class="cd-detail-section">
                            <span class="cd-detail-label">Rejection Reason</span>
                            <p class="cd-detail-text cd-detail-text--danger">{{ $viewData['rejection_reason'] }}</p>
                        </div>
                    @endif

                    @if (!empty($viewData['hired_employee']))
                        <div class="cd-detail-section">
                            <span class="cd-detail-label">Hired As</span>
                            <p class="cd-detail-value">
                                {{ $viewData['hired_employee']['first_name'] ?? '' }}
                                {{ $viewData['hired_employee']['last_name'] ?? '' }}
                                — {{ $viewData['hired_employee']['employee_number'] ?? '' }}
                            </p>
                        </div>
                    @endif

                    <div class="cd-detail-section">
                        <span class="cd-detail-label">Interviews ({{ count($viewData['interviews'] ?? []) }})</span>
                        @if (empty($viewData['interviews']))
                            <div class="cd-inline-empty">
                                <i class="fa-solid fa-circle-info"></i>
                                No interviews scheduled yet.
                            </div>
                        @else
                            <div class="cd-interviews">
                                @foreach ($viewData['interviews'] as $interview)
                                    <div class="cd-interview">
                                        <div class="cd-interview-icon">
                                            <i class="fa-solid fa-comments"></i>
                                        </div>
                                        <div class="cd-interview-body">
                                            <div class="cd-interview-title">
                                                Round {{ $interview['round'] ?? 1 }}
                                                · {{ ucfirst($interview['interview_type'] ?? 'video') }}
                                            </div>
                                            <div class="cd-interview-meta">
                                                {{ !empty($interview['scheduled_at']) ? \Carbon\Carbon::parse($interview['scheduled_at'])->format('d M Y H:i') : '—' }}
                                                @if (!empty($interview['interviewer']))
                                                    · {{ $interview['interviewer']['first_name'] ?? '' }} {{ $interview['interviewer']['last_name'] ?? '' }}
                                                @endif
                                            </div>
                                        </div>
                                        <x-nks.badge tone="muted">
                                            {{ ucfirst(str_replace('_', ' ', $interview['status'] ?? '')) }}
                                        </x-nks.badge>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                <footer class="cd-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    @if ($vCanAdvance)
                        <button type="button" wire:click="openAdvance({{ $viewData['id'] }})" class="nks-btn nks-btn--primary">
                            <i class="fa-solid fa-arrow-right"></i> Advance
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif

    {{-- create --}}
    @if ($showCreate)
        <div class="cd-modal-backdrop" wire:click.self="closeCreate">
            <div class="cd-modal cd-modal--lg">
                <header class="cd-modal-head">
                    <h3>Add Candidate</h3>
                    <button type="button" wire:click="closeCreate" class="cd-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="cd-modal-body">
                    @if ($parseWarning)
                        <div class="cd-parse-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <div>
                                <strong>Auto-fill failed</strong>
                                <p>{{ $parseWarning }}</p>
                            </div>
                        </div>
                    @endif

                    @if ($parsed)
                        <div class="cd-parse-success">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <strong>Resume parsed</strong>
                                <p>Review the auto-filled fields below before saving.</p>
                            </div>
                        </div>
                    @endif

                    <div class="cd-form-grid">
                        <div class="nks-field cd-form-full">
                            <label class="nks-label">Requisition <span class="nks-required">*</span></label>
                            <select wire:model="createForm.requisition_id" class="nks-select">
                                <option value="">Select requisition</option>
                                @foreach ($requisitions as $r)
                                    <option value="{{ $r['id'] }}">
                                        {{ $r['requisition_code'] ?? '' }} — {{ $r['title'] ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="nks-field cd-form-full">
                            <label class="nks-label">Resume (PDF, DOCX, TXT)</label>
                            <div class="cd-resume-row">
                                <input type="file" wire:model="resumeFile"
                                       accept=".pdf,.docx,.txt"
                                       class="nks-input" style="height: auto; padding: 8px;">
                                <button type="button"
                                        wire:click="parseResume"
                                        wire:loading.attr="disabled"
                                        wire:target="parseResume"
                                        class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                    {{ $parsing ? 'Parsing…' : 'Auto-fill from Resume' }}
                                </button>
                            </div>
                            <span class="nks-help">
                                Attach a resume and click Auto-fill to extract name, email, phone, skills, and experience.
                                Fields you have already typed are never overwritten.
                            </span>
                        </div>

                        <div class="nks-field">
                            <label class="nks-label">First Name <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.first_name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Last Name <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.last_name" class="nks-input">
                        </div>
                        <div class="nks-field cd-form-full">
                            <label class="nks-label">Email <span class="nks-required">*</span></label>
                            <input type="email" wire:model="createForm.email" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Phone</label>
                            <input type="text" wire:model="createForm.phone" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Source</label>
                            <select wire:model="createForm.source" class="nks-select">
                                <option value="manual">Manual</option>
                                <option value="linkedin">LinkedIn</option>
                                <option value="referral">Referral</option>
                                <option value="website">Website</option>
                                <option value="recruiter">Recruiter</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Years of Experience</label>
                            <input type="number" wire:model="createForm.years_experience" class="nks-input" min="0" max="60">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Expected Salary (R)</label>
                            <input type="number" wire:model="createForm.expected_salary" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field cd-form-full">
                            <label class="nks-label">LinkedIn URL</label>
                            <input type="url" wire:model="createForm.linkedin_url" class="nks-input"
                                   placeholder="https://linkedin.com/in/...">
                        </div>
                        <div class="nks-field cd-form-full">
                            <label class="nks-label">Skills (comma-separated)</label>
                            <input type="text" wire:model="createForm.skills" class="nks-input"
                                   placeholder="PHP, Laravel, MySQL, Redis">
                        </div>
                        <div class="nks-field cd-form-full">
                            <label class="nks-label">Notes</label>
                            <textarea wire:model="createForm.notes" class="nks-textarea"></textarea>
                        </div>
                    </div>
                </div>
                <footer class="cd-modal-foot">
                    <button type="button" wire:click="closeCreate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitCreate"
                            wire:loading.attr="disabled" wire:target="submitCreate"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i> Add Candidate
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- advance --}}
    @if ($showAdvance)
        <div class="cd-modal-backdrop" wire:click.self="closeAdvance">
            <div class="cd-modal">
                <header class="cd-modal-head">
                    <h3>Advance Candidate</h3>
                    <button type="button" wire:click="closeAdvance" class="cd-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="cd-modal-body">
                    <p class="cd-text">
                        Moving <strong>{{ $advanceName }}</strong> from
                        <strong>{{ ucfirst(str_replace('_', ' ', $advanceCurrentStage)) }}</strong>.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">New Stage <span class="nks-required">*</span></label>
                        <select wire:model="advanceStage" class="nks-select">
                            <option value="">Select new stage</option>
                            @foreach ($this->stageOptions() as $stage)
                                <option value="{{ $stage }}">{{ ucfirst(str_replace('_', ' ', $stage)) }}</option>
                            @endforeach
                        </select>
                        <span class="nks-help">
                            For rejections, use the Reject action so a reason is recorded.
                        </span>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Notes (optional)</label>
                        <textarea wire:model="advanceNotes" class="nks-textarea"
                                  placeholder="Context about the stage change."></textarea>
                    </div>
                </div>
                <footer class="cd-modal-foot">
                    <button type="button" wire:click="closeAdvance" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitAdvance" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-arrow-right"></i> Advance
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- reject --}}
    @if ($showReject)
        <div class="cd-modal-backdrop" wire:click.self="closeReject">
            <div class="cd-modal">
                <header class="cd-modal-head">
                    <h3>Reject Candidate</h3>
                    <button type="button" wire:click="closeReject" class="cd-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="cd-modal-body">
                    <p class="cd-text">
                        Reject <strong>{{ $rejectName }}</strong>?
                        This ends their application. The reason is visible to the recruiter team only.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Reason <span class="nks-required">*</span></label>
                        <textarea wire:model="rejectReason" class="nks-textarea"
                                  placeholder="Explain the rejection for the record."></textarea>
                    </div>
                </div>
                <footer class="cd-modal-foot">
                    <button type="button" wire:click="closeReject" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitReject" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-xmark"></i> Reject Candidate
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>