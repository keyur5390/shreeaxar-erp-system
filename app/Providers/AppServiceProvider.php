<?php

namespace App\Providers;

use App\Services\StorageService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->make(StorageService::class)->ensureDirectoriesExist();
    }
}
