<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The timezone that should be used by default for scheduled events.
     */
    protected $timezone = 'Africa/Kigali';

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('otp:clean')
            ->everyThirtyMinutes()
            ->when(fn () => config('app.cron_enabled', true));

        $schedule->command('quotations:send-reminders')
            ->dailyAt('09:00')
            ->timezone('Africa/Kigali');
    }
}
