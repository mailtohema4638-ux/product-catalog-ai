<?php

use App\DTOs\Money;
use App\DTOs\Product;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\ProviderUnavailableException;
use App\Infrastructure\Products\CachingProductProvider;
use App\Infrastructure\Products\InMemoryProductProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;
use Tests\Support\Make;
use Tests\Support\SpyProvider;

/** A store that really serialises, like the file/redis stores used in production. */
function serialisingCache(): Repository
{
    return Cache::build(['driver' => 'array', 'serialize' => true]);
}

function cachingProvider(SpyProvider $spy, ?LoggerInterface $logger = null): CachingProductProvider
{
    return new CachingProductProvider(
        inner: $spy,
        cache: serialisingCache(),
        logger: $logger ?? Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
        namespace: 'test',
        ttlSeconds: 600,
        staleTtlSeconds: 86400,
    );
}

function makeSpyProvider(): SpyProvider
{
    return new SpyProvider(new InMemoryProductProvider([
        Make::product('1', category: 'Tools'),
        Make::product('2', category: 'Garden'),
    ]));
}

it('serves repeat reads from cache', function () {
    $spy = makeSpyProvider();
    $provider = cachingProvider($spy);

    $provider->all();
    $provider->all();
    $provider->find('1');
    $provider->find('1');
    $provider->categories();
    $provider->categories();
    $provider->byCategory('tools');
    $provider->byCategory('tools');

    expect($spy->calls)->toBe(4);
});

it('returns real DTOs from a serialising store', function () {
    $provider = cachingProvider(makeSpyProvider());

    $provider->all();
    $cached = $provider->all();

    expect($cached[0])->toBeInstanceOf(Product::class)
        ->and($cached[0]->price)->toEqual(new Money(1000))
        ->and($provider->find('1'))->toBeInstanceOf(Product::class);
});

it('refetches after the fresh ttl expires', function () {
    $spy = makeSpyProvider();
    $provider = cachingProvider($spy);

    $provider->all();
    $this->travel(11)->minutes();
    $provider->all();

    expect($spy->calls)->toBe(2);
});

it('serves the stale copy when the source fails after the fresh ttl', function () {
    $spy = makeSpyProvider();
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')->once()->with(Mockery::pattern('/stale/i'), Mockery::type('array'));
    $provider = cachingProvider($spy, $logger);

    $provider->all();
    $this->travel(11)->minutes();
    $spy->failWith = new ProviderUnavailableException('down');

    expect($provider->all())->toHaveCount(2);
});

it('does not hammer a failing source: stale is re-armed briefly', function () {
    $spy = makeSpyProvider();
    $provider = cachingProvider($spy);

    $provider->all();
    $this->travel(11)->minutes();
    $spy->failWith = new ProviderUnavailableException('down');

    $provider->all();
    $provider->all();
    $provider->all();

    expect($spy->calls)->toBe(2);

    $this->travel(31)->seconds();
    $provider->all();

    expect($spy->calls)->toBe(3);
});

it('throws when the source fails and nothing is cached', function () {
    $spy = makeSpyProvider();
    $spy->failWith = new ProviderUnavailableException('down');

    cachingProvider($spy)->all();
})->throws(ProviderUnavailableException::class);

it('stops serving stale data after the stale ttl', function () {
    $spy = makeSpyProvider();
    $provider = cachingProvider($spy);

    $provider->all();
    $this->travel(25)->hours();
    $spy->failWith = new ProviderUnavailableException('down');

    $provider->all();
})->throws(ProviderUnavailableException::class);

it('does not mask not-found with stale data', function () {
    $spy = makeSpyProvider();
    $provider = cachingProvider($spy);

    $provider->find('9999');
})->throws(ProductNotFoundException::class);

it('caches products per id', function () {
    $spy = makeSpyProvider();
    $provider = cachingProvider($spy);

    expect($provider->find('1')->id)->toBe('1')
        ->and($provider->find('2')->id)->toBe('2')
        ->and($spy->calls)->toBe(2);
});
