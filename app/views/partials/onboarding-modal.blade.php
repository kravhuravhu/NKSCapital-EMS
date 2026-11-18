@if ($showOnboarding)
    <div class="em-modal-backdrop" wire:click.self="closeOnboarding">
        <div class="em-modal em-modal--lg">
            <header class="em-modal-head">
                <div>
                    <h3>Onboarding: {{ $onboardingName }}</h3>
                    <span class="em-modal-sub">
                        Progress {{ $onboardingProgress }}%
                        @if ($onboardingPendingEmail)
                            &middot; Awaiting company email
                        @endif
                    </span>
                </div>
                <button type="button" wire:click="closeOnboarding" class="em-modal-close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>
            <div class="em-modal-body">
                @if ($onboardingError)
                    <div class="nks-notice nks-notice--error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <div class="nks-notice-body">{{ $onboardingError }}</div>
                    </div>
                @endif

                <div class="nks-progress-bar" style="margin-bottom: 16px;">
                    <div class="nks-progress-fill" style="width: {{ $onboardingProgress }}%"></div>
                </div>

                <div class="nks-table-wrapper">
                    <table class="nks-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Status</th>
                                <th>Completed by</th>
                                <th style="width: 200px;">Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($onboardingItems as $item)
                                <tr wire:key="ob-{{ $item['id'] }}">
                                    <td>{{ $item['label'] }}</td>
                                    <td>
                                        <x-nks.badge :tone="match($item['status']) {
                                            'completed' => 'success',
                                            'in_progress' => 'gold',
                                            'not_applicable' => 'muted',
                                            default => 'blue',
                                        }">
                                            {{ ucfirst(str_replace('_', ' ', $item['status'])) }}
                                        </x-nks.badge>
                                    </td>
                                    <td>
                                        @if (! empty($item['completed_by']))
                                            {{ $item['completed_by']['first_name'] ?? '' }}
                                            {{ $item['completed_by']['last_name'] ?? '' }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <select wire:change="setOnboardingStatus('{{ $item['item_key'] }}', $event.target.value)"
                                                class="nks-select">
                                            <option value="pending" @selected($item['status'] === 'pending')>Pending</option>
                                            <option value="in_progress" @selected($item['status'] === 'in_progress')>In progress</option>
                                            <option value="completed" @selected($item['status'] === 'completed')>Completed</option>
                                            <option value="not_applicable" @selected($item['status'] === 'not_applicable')>Not applicable</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="em-modal-foot">
                <button type="button" wire:click="closeOnboarding" class="nks-btn nks-btn--outline">
                    Close
                </button>
            </footer>
        </div>
    </div>
@endif