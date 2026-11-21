<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->get('/api/_client-ip', fn (Request $request) => $request->ip());
});

afterEach(fn () => TrustProxies::flushState());

function clientIpSeenBy(object $test, string $forwardedFor): string
{
    return $test->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
        ->get('/api/_client-ip', ['X-Forwarded-For' => $forwardedFor])
        ->getContent();
}

it('ignores X-Forwarded-For by default, so clients cannot spoof their address', function () {
    expect(clientIpSeenBy($this, '203.0.113.9'))->toBe('10.0.0.7');
});

it('uses the forwarded address when the sender is a trusted proxy', function () {
    config(['http.trusted_proxies' => ['10.0.0.0/8']]);
    app(AppServiceProvider::class, ['app' => $this->app])->trustGatewayProxies();

    expect(clientIpSeenBy($this, '203.0.113.9'))->toBe('203.0.113.9');
});

it('does not trust a proxy that is not configured', function () {
    config(['http.trusted_proxies' => ['192.168.0.0/16']]);
    app(AppServiceProvider::class, ['app' => $this->app])->trustGatewayProxies();

    expect(clientIpSeenBy($this, '203.0.113.9'))->toBe('10.0.0.7');
});
