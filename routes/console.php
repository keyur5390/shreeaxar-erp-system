<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('model:prune')->daily()->when(fn () => (bool) env('CRON_ENABLED', true));
