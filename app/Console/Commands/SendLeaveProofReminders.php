<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LeaveProofService;

class SendLeaveProofReminders extends Command
{
    protected $signature = 'leave:send-proof-reminders';
    protected $description = 'Send reminders for pending leave proof uploads and convert overdue ones to unpaid';

    public function handle(LeaveProofService $service): int
    {
        $result = $service->sendDueReminders();

        $this->info(sprintf(
            'Reminders: employee=%d, warnings=%d, conversions=%d',
            $result['employee'],
            $result['warnings'],
            $result['conversions']
        ));

        return self::SUCCESS;
    }
}