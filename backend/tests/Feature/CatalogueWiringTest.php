<?php

use App\Contracts\ProductProviderInterface;
use App\Infrastructure\Products\CachingProductProvider;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeStore;

/*
 * No test doubles for our own classes here: real container bindings, real FakeStoreProvider
 * and caching decorator, with only the network faked.
 */
beforeEach(function () {
    config([
        'catalogue.provider' => 'fakestore',
        'catalogue.fakestore.base_url' => FakeStore::BASE_URL,
        'catalogue.fakestore.retry_sleep_ms' => 0,
        'catalogue.featured_product_ids' => ['3', '7', '5', '1', '2'],
    ]);
});

it('serves the home page end to end from the Fake Store source', function () {
    FakeStore::fake();

    $this->getJson('/api/v1/featured')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.*.id', ['3', '7', '5', '1', '2'])
        ->assertJsonPath('data.0.price.amount_minor', 5599);
});

it('serves a category whose source name has an apostrophe', function () {
    FakeStore::fake();

    $this->getJson('/api/v1/categories/mens-clothing')
        ->assertOk()
        ->assertJsonPath('data.category.name', "Men's Clothing")
        ->assertJsonPath('data.top_rated.*.id', ['3', '2', '1'])
        ->assertJsonPath('data.others.*.id', ['4']);
});

it('searches across the live catalogue shape', function () {
    FakeStore::fake();

    $this->getJson('/api/v1/products?q=gold')->assertOk()->assertJsonPath('data.*.id', ['5', '6']);
});

it('caches so repeat requests do not hit the source again', function () {
    FakeStore::fake();

    $this->getJson('/api/v1/featured')->assertOk();
    $this->getJson('/api/v1/featured')->assertOk();
    $this->getJson('/api/v1/products?q=gold')->assertOk();

    Http::assertSentCount(1);
});

it('binds the product provider to a caching decorator around the configured source', function () {
    expect(app(ProductProviderInterface::class))->toBeInstanceOf(CachingProductProvider::class);
});

it('fails fast on an unknown provider name', function () {
    config(['catalogue.provider' => 'nope']);

    app(ProductProviderInterface::class);
})->throws(InvalidArgumentException::class, 'Unknown catalogue provider [nope]');

it('degrades to a friendly 503 when the source is down and nothing is cached', function () {
    Http::fake(['*' => Http::response('', 503)]);

    $this->getJson('/api/v1/featured')
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'catalogue_unavailable');
});

it('keeps serving cached data when the source goes down', function () {
    FakeStore::fake();
    $this->getJson('/api/v1/featured')->assertOk();

    $this->travel(11)->minutes();
    Http::fake(['*' => Http::response('', 503)]);

    $this->getJson('/api/v1/featured')->assertOk()->assertJsonCount(5, 'data');
});
