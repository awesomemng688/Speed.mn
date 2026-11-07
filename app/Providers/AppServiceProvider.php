<?php

namespace App\Providers;

use App\Models\ServerStatus;
use App\Observers\ServerStatusObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ServerStatus::observe(ServerStatusObserver::class);
    }
}
