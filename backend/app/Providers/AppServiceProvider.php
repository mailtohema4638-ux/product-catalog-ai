<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Enquiries send email, so they get a much tighter limit than reads.
        RateLimiter::for('enquiries', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('catalogue-read', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
    }
}
