<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->trustGatewayProxies();

        // Enquiries send email, so they get a much tighter limit than reads.
        RateLimiter::for('enquiries', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('catalogue-read', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
    }

    /**
     * Behind the gateway the real client IP arrives in X-Forwarded-For, and per-IP throttling
     * depends on it. Only the configured proxies are trusted (none by default). Done here, not in
     * bootstrap/app.php, so it reads config and keeps working when config is cached.
     */
    public function trustGatewayProxies(): void
    {
        $proxies = config('http.trusted_proxies');

        if ($proxies !== []) {
            TrustProxies::at(in_array('*', $proxies, true) ? '*' : $proxies);
        }
    }
}
