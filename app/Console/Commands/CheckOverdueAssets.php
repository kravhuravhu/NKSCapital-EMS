<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AssetService;

class CheckOverdueAssets extends Command
{
    protected $signature = 'assets:check-overdue';
    protected $description = 'Check for overdue loaned assets and escalate notifications (3/7/14 days)';

    public function handle(AssetService $service): int
    {
        $result = $service->escalateOverdue();

        $this->info(sprintf(
            'Overdue escalations: L3=%d, L7=%d, L14=%d',
            $result['level3'],
            $result['level7'],
            $result['level14']
        ));

        return self::SUCCESS;
    }
}