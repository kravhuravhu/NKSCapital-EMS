<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PRTimesheet;
use App\Models\PSTimesheet;
use App\Models\User;
use App\Services\NotificationService;

class GenerateReminders extends Command
{
    protected $signature = 'reminders:generate
                            {--type=all : all|timesheet|leave|contract|asset}
                            {--urgent : Send urgent reminder}';

    protected $description = 'Generate reminder notifications across modules';

    public function handle(NotificationService $notifications): int
    {
        $type = $this->option('type');
        $urgent = $this->option('urgent');
        $month = now()->startOfMonth()->format('Y-m-d');

        $count = 0;

        if (in_array($type, ['all', 'timesheet'])) {
            // Employees who have NOT submitted their timesheet this month
            $submitted = PRTimesheet::where('month_year', $month)->pluck('user_id')
                ->merge(PSTimesheet::where('month_year', $month)->pluck('user_id'))
                ->unique();

            $nonSubmitters = User::where('is_active', true)
                ->whereIn('employee_type', ['ps', 'pr'])
                ->whereNotIn('id', $submitted)
                ->get();

            foreach ($nonSubmitters as $u) {
                $notifications->send(
                    $u,
                    $urgent ? 'TIMESHEET_REMINDER_URGENT' : 'TIMESHEET_REMINDER',
                    $urgent ? 'URGENT: Timesheet Due Tomorrow' : 'Timesheet Reminder',
                    $urgent
                        ? 'Your timesheet is due on the 29th. Please submit it urgently.'
                        : 'Please remember to submit your timesheet before the 29th.',
                    'timesheet',
                    $urgent ? 'urgent' : 'normal',
                    '/timesheet',
                    'Submit Timesheet',
                    null,
                    null,
                    'timesheet_reminder_' . now()->format('Y-m')
                );
                $count++;
            }
        }

        $this->info("Reminders generated: {$count}");

        return self::SUCCESS;
    }
}