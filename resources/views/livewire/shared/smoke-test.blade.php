<div>
    <div class="split-layout">
        <div class="stack">
            <x-nks.block title="API Connection">
                @if ($apiOk)
                    <div class="notice" style="background: var(--mint); color: #1F6B45;">
                        <i class="fa-solid fa-circle-check" style="font-size: 20px;"></i>
                        <div>
                            <strong style="color: #1F6B45;">API Connected</strong>
                            <p>Livewire → Server → Sanctum token → /api/v1. All successful.</p>
                        </div>
                    </div>
                @else
                    <div class="notice" style="background: var(--red); color: #8A2A2A;">
                        <i class="fa-solid fa-circle-exclamation" style="font-size: 20px;"></i>
                        <div>
                            <strong style="color: #8A2A2A;">API Failed</strong>
                            <p>{{ $apiError }}</p>
                        </div>
                    </div>
                @endif

                <div class="mt-4" style="font-size: 14px;">
                    <p><strong>User:</strong> {{ auth()->user()->full_name }}</p>
                    <p class="mt-2"><strong>Role:</strong> {{ auth()->user()->role }}</p>
                    <p class="mt-2">
                        <strong>Token present:</strong>
                        <x-nks.badge :tone="\App\Support\Api::token() ? 'green' : 'red'">
                            {{ \App\Support\Api::token() ? 'yes' : 'no' }}
                        </x-nks.badge>
                    </p>
                </div>
            </x-nks.block>

            <x-nks.block title="Pending Counts">
                @forelse ($pendingCounts as $key => $value)
                    <div class="info-row">
                        <x-nks.dot />
                        <span>{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                        <strong>{{ $value }}</strong>
                    </div>
                @empty
                    <p class="text-muted">No pending counts returned.</p>
                @endforelse
            </x-nks.block>
        </div>

        <div class="stack">
            <x-nks.block title="Roles from API">
                @forelse ($permissionsData['roles'] ?? [] as $role)
                    <x-nks.badge tone="gold">{{ $role }}</x-nks.badge>
                @empty
                    <p class="text-muted">No roles returned.</p>
                @endforelse
            </x-nks.block>

            <x-nks.block :title="'Permissions (' . count($permissionsData['permissions'] ?? []) . ')'">
                <div style="display: flex; flex-wrap: wrap; gap: 4px; max-height: 260px; overflow-y: auto;">
                    @foreach ($permissionsData['permissions'] ?? [] as $permission)
                        <x-nks.badge tone="purple">{{ $permission }}</x-nks.badge>
                    @endforeach
                </div>
            </x-nks.block>
        </div>
    </div>
</div>