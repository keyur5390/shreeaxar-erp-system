<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune')->daily()->when(fn () => config('app.cron_enabled', true));
Schedule::command('otp:clean')->everyThirtyMinutes()->when(fn () => config('app.cron_enabled', true));
Schedule::command('quotations:send-reminders')->dailyAt('09:00')->timezone('Africa/Kigali');
