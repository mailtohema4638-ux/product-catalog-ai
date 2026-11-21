<?php

use App\Contracts\ProductProviderInterface;
use App\DTOs\CatalogueSettings;
use App\Exceptions\ProviderRateLimitedException;
use App\Exceptions\ProviderUnavailableException;
use App\Infrastructure\Products\InMemoryProductProvider;
use Tests\Support\Make;

beforeEach(function () {
    $this->app->instance(ProductProviderInterface::class, new InMemoryProductProvider([
        Make::product('1', 'Red Hammer', 'Tools', 4.9, 500, 1999),
        Make::product('2', 'Blue Hammer', 'Tools', 4.0, 100, 1500),
        Make::product('3', 'Garden Rose', 'Garden', 3.0, 20, 800, 'Red and fragrant.'),
        Make::product('4', 'Spade', 'Garden', 4.5, 300, 2500),
        Make::product('5', 'Saw', 'Tools', 2.0, 10, 1200),
        Make::product('6', 'Wrench', 'Tools', 4.2, 80, 900),
    ]));
    $this->app->instance(CatalogueSettings::class, new CatalogueSettings(['4', '1', '3'], categoryTopRatedCount: 2));
});

describe('home page', function () {
    it('shows exactly the curated featured products in order', function () {
        $this->getJson('/api/v1/featured')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.*.id', ['4', '1', '3']);
    });

    it('shapes a product for the client', function () {
        $this->getJson('/api/v1/featured')->assertJsonPath('data.1', [
            'id' => '1',
            'title' => 'Red Hammer',
            'description' => 'A product.',
            'price' => ['amount_minor' => 1999, 'currency' => 'USD'],
            'category' => ['slug' => 'tools', 'name' => 'Tools'],
            'image_url' => 'https://images.test/1.jpg',
            'rating' => ['average' => 4.9, 'count' => 500],
        ]);
    });
});

describe('category page', function () {
    it('lists categories', function () {
        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.*.slug', ['garden', 'tools']);
    });

    it('shows top rated products first, then the additional products', function () {
        $this->getJson('/api/v1/categories/tools')
            ->assertOk()
            ->assertJsonPath('data.category.name', 'Tools')
            ->assertJsonPath('data.top_rated.*.id', ['1', '6'])
            ->assertJsonPath('data.others.*.id', ['2', '5']);
    });

    it('returns a 404 envelope for an unknown category', function () {
        $this->getJson('/api/v1/categories/nope')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    });
});

describe('search', function () {
    it('returns only products that match the term', function () {
        $this->getJson('/api/v1/products?q=red')
            ->assertOk()
            ->assertJsonPath('data.*.id', ['1', '3']);
    });

    it('can be scoped to a category', function () {
        $this->getJson('/api/v1/products?q=red&category=garden')
            ->assertOk()
            ->assertJsonPath('data.*.id', ['3']);
    });

    it('returns an empty list when nothing matches', function () {
        $this->getJson('/api/v1/products?q=zzz')->assertOk()->assertExactJson(['data' => []]);
    });

    it('normalises surrounding and repeated whitespace', function () {
        $this->getJson('/api/v1/products?q='.urlencode('  red   hammer '))
            ->assertOk()
            ->assertJsonPath('data.*.id', ['1']);
    });

    it('rejects invalid input with field errors', function (string $query, string $field) {
        $this->getJson("/api/v1/products{$query}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonValidationErrors([$field], 'error.details');
    })->with([
        'missing term' => ['', 'q'],
        'blank term' => ['?q=%20%20', 'q'],
        'term too long' => ['?q='.str_repeat('a', 101), 'q'],
        'array term' => ['?q[]=a', 'q'],
        'bad category' => ['?q=a&category=../etc', 'category'],
    ]);
});

describe('product page', function () {
    it('returns the product details', function () {
        $this->getJson('/api/v1/products/3')
            ->assertOk()
            ->assertJsonPath('data.title', 'Garden Rose');
    });

    it('returns a 404 envelope for an unknown product', function () {
        $this->getJson('/api/v1/products/999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    });

    it('does not route malformed ids to the catalogue', function (string $id) {
        $this->getJson("/api/v1/products/{$id}")->assertNotFound();
    })->with(['spaces' => 'a%20b', 'dots' => '..', 'too long' => str_repeat('a', 65)]);
});

describe('error envelope', function () {
    it('carries a request id that matches the response header', function () {
        $response = $this->getJson('/api/v1/products/999');

        $response->assertJsonPath('error.request_id', $response->headers->get('X-Request-Id'));
        expect($response->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f-]{36}$/');
    });

    it('never trusts an inbound request id', function () {
        $response = $this->getJson('/api/v1/featured', ['X-Request-Id' => "evil\nforged"]);

        expect($response->headers->get('X-Request-Id'))->not->toContain('evil');
    });

    it('returns a JSON 404 for unknown api routes', function () {
        $this->getJson('/api/v1/nope')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    });

    it('returns a JSON 405 for wrong methods', function () {
        $this->postJson('/api/v1/featured')->assertStatus(405)->assertJsonPath('error.code', 'method_not_allowed');
    });

    it('returns 503 with Retry-After when the product source is down', function () {
        $this->app->instance(ProductProviderInterface::class, Mockery::mock(ProductProviderInterface::class, function ($m) {
            $m->shouldReceive('all')->andThrow(new ProviderUnavailableException('cURL error 28 at 10.0.0.5'));
        }));

        $this->getJson('/api/v1/featured')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '30')
            ->assertJsonPath('error.code', 'catalogue_unavailable')
            ->assertJsonMissingPath('error.details');
    });

    it('passes the upstream Retry-After through when rate limited', function () {
        $this->app->instance(ProductProviderInterface::class, Mockery::mock(ProductProviderInterface::class, function ($m) {
            $m->shouldReceive('all')->andThrow(new ProviderRateLimitedException(9));
        }));

        $this->getJson('/api/v1/featured')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '9')
            ->assertJsonPath('error.code', 'catalogue_busy');
    });

    it('hides internals on unexpected failures', function () {
        $this->app->instance(ProductProviderInterface::class, Mockery::mock(ProductProviderInterface::class, function ($m) {
            $m->shouldReceive('all')->andThrow(new RuntimeException('database password is hunter2'));
        }));

        $response = $this->getJson('/api/v1/featured')
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'server_error');

        expect($response->getContent())->not->toContain('hunter2')->not->toContain('RuntimeException');
    });
});

describe('http caching and rate limiting', function () {
    it('lets clients cache successful reads briefly', function () {
        $this->getJson('/api/v1/featured')->assertHeader('Cache-Control', 'max-age=60, public');
    });

    it('does not let clients cache errors', function () {
        expect($this->getJson('/api/v1/products/999')->headers->get('Cache-Control'))->not->toContain('public');
    });

    it('throttles read endpoints per client with a JSON 429', function () {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/featured')->assertOk();
        }

        $this->getJson('/api/v1/featured')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'too_many_requests');
    });
});
