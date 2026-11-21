<?php

use App\Exceptions\ProductNotFoundException;
use App\Exceptions\ProviderRateLimitedException;
use App\Exceptions\ProviderUnavailableException;
use App\Exceptions\UnexpectedProviderResponseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeStore;

it('identifies itself and asks for JSON', function () {
    FakeStore::fake();

    FakeStore::provider()->all();

    Http::assertSent(fn (Request $r): bool => $r->hasHeader('User-Agent', 'ProductCatalogue/1.0')
        && $r->hasHeader('Accept', 'application/json'));
});

it('url-encodes category names that contain spaces and apostrophes', function () {
    FakeStore::fake();

    FakeStore::provider()->byCategory('mens-clothing');

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/products/category/men%27s%20clothing'));
});

it('treats the Fake Store empty-body 200 as product not found', function () {
    FakeStore::fake();

    FakeStore::provider()->find('9999');
})->throws(ProductNotFoundException::class);

it('treats an upstream 404 as product not found', function () {
    Http::fake(['*' => Http::response('', 404)]);

    FakeStore::provider()->find('1');
})->throws(ProductNotFoundException::class);

it('reports upstream rate limiting with its Retry-After and does not retry', function () {
    Http::fake(['*' => Http::response('slow down', 429, ['Retry-After' => '7'])]);

    try {
        FakeStore::provider()->all();
        $this->fail('Expected ProviderRateLimitedException');
    } catch (ProviderRateLimitedException $e) {
        expect($e->retryAfterSeconds)->toBe(7)->and($e)->toBeInstanceOf(ProviderUnavailableException::class);
    }

    Http::assertSentCount(1);
});

it('retries server errors a bounded number of times, then reports unavailable', function () {
    Http::fake(['*' => Http::response('boom', 500)]);

    try {
        FakeStore::provider(retries: 1)->all();
        $this->fail('Expected ProviderUnavailableException');
    } catch (ProviderUnavailableException $e) {
        expect($e->getMessage())->not->toContain('boom');
    }

    Http::assertSentCount(2);
});

it('recovers when a retry succeeds', function () {
    Http::fake(['*' => Http::sequence()->push('', 502)->push(FakeStore::items())]);

    expect(FakeStore::provider(retries: 1)->all())->toHaveCount(7);
});

it('does not retry client errors', function () {
    Http::fake(['*' => Http::response('nope', 403)]);

    try {
        FakeStore::provider()->all();
    } catch (ProviderUnavailableException) {
    }

    Http::assertSentCount(1);
});

it('maps network failures to unavailable without leaking transport details', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out with secret-host'));

    try {
        FakeStore::provider()->all();
        $this->fail('Expected ProviderUnavailableException');
    } catch (ProviderUnavailableException $e) {
        expect($e->getMessage())->not->toContain('secret-host');
    }
});

it('rejects a body that is not valid JSON', function () {
    Http::fake(['*' => Http::response('<html>Cloudflare</html>', 200)]);

    FakeStore::provider()->all();
})->throws(UnexpectedProviderResponseException::class);

it('rejects a product list that is not a list', function () {
    Http::fake(['*' => Http::response(['error' => 'x'], 200)]);

    FakeStore::provider()->all();
})->throws(UnexpectedProviderResponseException::class);

it('skips a malformed product instead of failing the whole catalogue', function () {
    $items = FakeStore::items();
    $items[2]['price'] = 'free';
    FakeStore::fake($items);

    $ids = array_map(fn ($p) => $p->id, FakeStore::provider()->all());

    expect($ids)->toBe(['1', '2', '4', '5', '6', '7']);
});
