<?php

use App\Contracts\EmailProviderInterface;
use App\Contracts\ProductProviderInterface;
use App\Infrastructure\Products\CachingProductProvider;
use App\Infrastructure\Products\JsonCatalogueProvider;

/*
 * The bundled office catalogue through the real container bindings, with no test doubles.
 * Http::preventStrayRequests() (see Pest.php) also proves this source never touches the network.
 */
beforeEach(function () {
    config([
        'catalogue.provider' => 'local',
        'catalogue.featured_product_ids' => [
            'a4-copy-paper-500', 'wireless-anc-headphones', 'height-adjustable-standing-desk', 'canvas-print-a2', 'gel-pens-assorted-10pk',
        ],
    ]);
});

it('binds the local catalogue without a cache layer', function () {
    expect(app(ProductProviderInterface::class))
        ->toBeInstanceOf(JsonCatalogueProvider::class)
        ->not->toBeInstanceOf(CachingProductProvider::class);
});

describe('home page', function () {
    it('shows the 5 chosen top-rated products, in order, from different categories', function () {
        $response = $this->getJson('/api/v1/featured')->assertOk()->assertJsonCount(5, 'data');

        expect($response->json('data.*.id'))->toBe([
            'a4-copy-paper-500', 'wireless-anc-headphones', 'height-adjustable-standing-desk', 'canvas-print-a2', 'gel-pens-assorted-10pk',
        ])->and(collect($response->json('data'))->pluck('category.slug')->unique()->count())->toBeGreaterThan(1);
    });
});

describe('category page', function () {
    it('lists the four categories', function () {
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.*.name', [
            'Office Furniture', 'Office Supplies & Stationery', 'Print & Photo Services', 'Tech & Electronics',
        ]);
    });

    it('shows the top-rated products first, then the additional products', function () {
        $response = $this->getJson('/api/v1/categories/office-furniture')->assertOk();

        $top = $response->json('data.top_rated');
        $others = $response->json('data.others');

        expect($top)->toHaveCount(3)
            ->and($top[0]['id'])->toBe('height-adjustable-standing-desk')
            ->and($others)->not->toBeEmpty()
            ->and(min(array_column(array_column($top, 'rating'), 'average')))
            ->toBeGreaterThanOrEqual(max(array_column(array_column($others, 'rating'), 'average')));
    });

    it('only contains products from that category', function () {
        $response = $this->getJson('/api/v1/categories/print-photo-services')->assertOk();

        expect(collect([...$response->json('data.top_rated'), ...$response->json('data.others')])
            ->pluck('category.slug')->unique()->all())->toBe(['print-photo-services']);
    });
});

describe('search', function () {
    it('finds products by words in the title', function () {
        $this->getJson('/api/v1/products?q=pens')->assertOk()
            ->assertJsonPath('data.*.id', ['ballpoint-pens-blue-12pk', 'gel-pens-assorted-10pk']);
    });

    it('finds products by words in the description, across categories', function () {
        expect($this->getJson('/api/v1/products?q=wi-fi')->json('data.*.category.slug'))->toContain('tech-electronics');
    });

    it('matches the category name', function () {
        $ids = $this->getJson('/api/v1/products?q=furniture')->assertOk()->json('data.*.category.slug');

        expect(array_unique($ids))->toBe(['office-furniture']);
    });

    it('can be narrowed to a category', function () {
        $this->getJson('/api/v1/products?q=a4&category=office-supplies-stationery')->assertOk()
            ->assertJsonPath('data.0.category.slug', 'office-supplies-stationery');
    });

    it('returns nothing for a term that matches nothing', function () {
        $this->getJson('/api/v1/products?q=submarine')->assertOk()->assertExactJson(['data' => []]);
    });
});

describe('product page', function () {
    it('shows the details of a product', function () {
        $this->getJson('/api/v1/products/monitor-27-qhd-usb-hub')->assertOk()
            ->assertJsonPath('data.title', '27" QHD Monitor with USB Hub')
            ->assertJsonPath('data.price', ['amount_minor' => 32900, 'currency' => 'USD'])
            ->assertJsonPath('data.image_url', '/images/categories/tech-electronics.svg');
    });

    it('returns 404 for an unknown product', function () {
        $this->getJson('/api/v1/products/no-such-product')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    });
});

it('lets customers enquire about a catalogue product', function () {
    config(['enquiry.email_provider' => 'mock']);

    $this->postJson('/api/v1/enquiries', [
        'product_id' => 'ergonomic-mesh-office-chair', 'name' => 'Jane Doe', 'email' => 'jane@example.com',
    ])->assertCreated();

    $email = app(EmailProviderInterface::class)->sent[0];
    expect($email->subject)->toBe('New enquiry: Ergonomic Mesh Office Chair')->and($email->body)->toContain('USD 249.00');
});
