<?php

namespace App\Console\Commands;

use App\Models\Fee;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class FeeDueDateReminder extends Command
{
    protected $signature = 'notifications:fees-due {--days=3 : How many days ahead to look for due fees}';

    protected $description = 'Notify student and officer/head users about fees due within the next N days';

    public function __construct(
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));

        $fees = Fee::query()
            ->posted()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [
                now()->startOfDay(),
                now()->addDays($days)->endOfDay(),
            ])
            ->get();

        $sent = 0;
        foreach ($fees as $fee) {
            $this->notificationService->notifyFeeDue($fee);
            $sent++;
        }

        $this->info("Sent due-date reminders for {$sent} fee(s).");

        return self::SUCCESS;
    }
}
