<div class="page-import-employees">
    <x-nks.loading-overlay targets="previewImport,executeImport" />
    <x-nks.notice-stack :error="$error" :success="$flash" />

    <div class="panel">
        <div class="page-toolbar">
            <div>
                <h2>Bulk import employees</h2>
                <p>Upload a CSV to create many employee accounts at once. Every row is validated individually.</p>
            </div>
            <button type="button" wire:click="downloadTemplate" class="nks-btn nks-btn--outline nks-btn--sm">
                <i class="fa-solid fa-download"></i>
                Download template
            </button>
        </div>

        @if (! $imported)
            <div class="nks-field" style="margin-bottom: 16px;">
                <label class="nks-label">CSV file <span class="nks-required">*</span></label>
                <input type="file" wire:model="csvFile" accept=".csv,text/csv" class="nks-input"
                       style="height: auto; padding: 8px;">
                <span class="nks-help">Max 10 MB. First row must be the header. Use the template as a starting point.</span>
            </div>

            <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                <button type="button" wire:click="previewImport"
                        wire:loading.attr="disabled" wire:target="previewImport"
                        class="nks-btn nks-btn--primary">
                    <i class="fa-solid fa-eye" wire:loading.remove wire:target="previewImport"></i>
                    <span class="nks-btn-spinner" wire:loading wire:target="previewImport"></span>
                    Preview
                </button>

                @if ($hasPreviewed && $previewCreated > 0)
                    <button type="button" wire:click="openConfirm" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        Import {{ $previewCreated }} {{ \Illuminate\Support\Str::plural('row', $previewCreated) }}
                    </button>
                @endif
            </div>
        @endif

        @if ($hasPreviewed && count($preview) > 0)
            <div class="nks-table-wrapper">
                <table class="nks-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Row</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview as $row)
                            <tr>
                                <td>{{ $row['row_number'] ?? '' }}</td>
                                <td>{{ $row['email'] ?? '-' }}</td>
                                <td>
                                    <x-nks.badge :tone="match($row['status'] ?? '') {
                                        'ready' => 'info',
                                        'created' => 'success',
                                        'error' => 'red',
                                        default => 'muted',
                                    }">
                                        {{ ucfirst($row['status'] ?? '') }}
                                    </x-nks.badge>
                                </td>
                                <td>{{ $row['message'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (! $imported && $previewSkipped > 0)
                <div class="nks-notice nks-notice--warning" style="margin-top: 16px;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div class="nks-notice-body">
                        {{ $previewSkipped }} {{ \Illuminate\Support\Str::plural('row', $previewSkipped) }}
                        will be skipped. Fix the CSV and re-upload, or import only the ready rows.
                    </div>
                </div>
            @endif
        @endif

        @if ($imported)
            <div style="margin-top: 16px;">
                <button type="button" wire:click="resetImport" class="nks-btn nks-btn--outline">
                    <i class="fa-solid fa-rotate-left"></i>
                    Import another file
                </button>
            </div>
        @endif
    </div>

    @if ($showConfirm)
        <div class="nks-modal-backdrop" wire:click.self="closeConfirm">
            <div class="nks-modal nks-modal--sm">
                <header class="nks-modal-head">
                    <h3>Confirm import</h3>
                    <button type="button" wire:click="closeConfirm" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <p style="font-size: 13px; margin: 0 0 12px;">
                        {{ $previewCreated }} employees will be created. This creates real accounts and sends
                        welcome emails. Confirm with your admin password.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Admin password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="confirmPassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeConfirm" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="executeImport"
                            wire:loading.attr="disabled" wire:target="executeImport"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-cloud-arrow-up" wire:loading.remove wire:target="executeImport"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="executeImport"></span>
                        Import now
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>