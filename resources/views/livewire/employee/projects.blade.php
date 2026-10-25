<div class="page-projects">

    @if ($error)
        <div class="pj-flash pj-flash--error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    @if ($loading)
        <x-nks.spinner block label="Loading projects…" />
    @else

        <div class="panel pj-panel">
            <div class="pj-header">
                <i class="fa-solid fa-diagram-project pj-header-icon"></i>
                <div>
                    <h2>My Projects</h2>
                    <p>All projects you are currently assigned to</p>
                </div>
            </div>

            @if ($primary)
                <div class="pj-primary">
                    <div class="pj-primary-icon">
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <div class="pj-primary-info">
                        <div class="pj-primary-label">Primary Project</div>
                        <div class="pj-primary-name">{{ $primary['name'] ?? '—' }}</div>
                        <div class="pj-primary-code">{{ $primary['project_code'] ?? '' }}</div>
                    </div>
                    <x-nks.badge :tone="\App\Livewire\Employee\Projects::statusTone($primary['status'] ?? null)">
                        {{ \App\Livewire\Employee\Projects::statusLabel($primary['status'] ?? null) }}
                    </x-nks.badge>
                </div>
            @endif

            {{-- table --}}
            @if (count($projects) === 0)
                <div class="pj-empty">
                    <i class="fa-solid fa-diagram-project"></i>
                    <strong>No projects assigned</strong>
                    <span>You haven't been assigned to any projects yet. Contact your manager if this is unexpected.</span>
                </div>
            @else
                <div class="pj-table-wrapper">
                    <table class="pj-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Code</th>
                                <th>Role</th>
                                <th>Allocation</th>
                                <th>Assigned</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $assignment)
                                @php
                                    $p = $assignment['project'] ?? [];
                                @endphp
                                <tr>
                                    <td>
                                        <div class="pj-name">
                                            <span class="pj-name-icon">
                                                <i class="fa-solid fa-diagram-project"></i>
                                            </span>
                                            <span>{{ $p['name'] ?? '—' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="pj-code">{{ $p['project_code'] ?? '—' }}</span>
                                    </td>
                                    <td>
                                        {{ ucfirst(str_replace('_', ' ', $assignment['role'] ?? 'team member')) }}
                                    </td>
                                    <td>
                                        {{ $assignment['allocation'] ?? 100 }}%
                                    </td>
                                    <td>
                                        {{ isset($assignment['assigned_date']) ? \Carbon\Carbon::parse($assignment['assigned_date'])->format('d M Y') : '—' }}
                                    </td>
                                    <td>
                                        <x-nks.badge :tone="\App\Livewire\Employee\Projects::statusTone($p['status'] ?? null)">
                                            {{ \App\Livewire\Employee\Projects::statusLabel($p['status'] ?? null) }}
                                        </x-nks.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>