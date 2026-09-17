<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ContractService;

class CheckContractExpiry extends Command
{
    protected $signature = 'contracts:check-expiry';
    protected $description = 'Send contract expiry alerts (30/14/7 days) and auto-expire stale contracts';

    public function handle(ContractService $service): int
    {
        $result = $service->sendExpiryAlerts();

        $this->info(sprintf(
            'Contract expiry alerts sent: L30=%d, L14=%d, L7=%d',
            $result['30'],
            $result['14'],
            $result['7']
        ));

        return self::SUCCESS;
    }
}