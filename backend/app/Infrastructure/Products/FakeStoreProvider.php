<?php

namespace App\Infrastructure\Products;

use App\Contracts\ProductProviderInterface;
use App\DTOs\Category;
use App\DTOs\Product;
use App\Exceptions\CategoryNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\ProviderRateLimitedException;
use App\Exceptions\ProviderUnavailableException;
use App\Exceptions\UnexpectedProviderResponseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;

/** Adapts https://fakestoreapi.com to the application's ProductProviderInterface. */
class FakeStoreProvider implements ProductProviderInterface
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly FakeStoreMapper $mapper,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds = 5,
        private readonly int $retries = 2,
        private readonly int $retrySleepMs = 200,
    ) {}

    public function all(): array
    {
        $data = $this->get('/products');

        if (! is_array($data) || ! array_is_list($data)) {
            throw new UnexpectedProviderResponseException('Product list was not a JSON list.');
        }

        return $this->mapMany($data);
    }

    public function find(string $id): Product
    {
        $data = $this->get('/products/'.rawurlencode($id));

        // The Fake Store API answers 200 with an empty body for unknown ids rather than 404.
        if ($data === null || $data === []) {
            throw ProductNotFoundException::withId($id);
        }
        if (! is_array($data)) {
            throw new UnexpectedProviderResponseException('Product was not a JSON object.');
        }

        return $this->mapper->product($data);
    }

    public function byCategory(string $categorySlug): array
    {
        $sourceName = null;
        foreach ($this->sourceCategoryNames() as $name) {
            if ($this->mapper->category($name)->slug === $categorySlug) {
                $sourceName = $name;
                break;
            }
        }

        if ($sourceName === null) {
            throw CategoryNotFoundException::withSlug($categorySlug);
        }

        $data = $this->get('/products/category/'.rawurlencode($sourceName));

        return is_array($data) && array_is_list($data) ? $this->mapMany($data) : [];
    }

    public function categories(): array
    {
        return array_map(fn (string $name): Category => $this->mapper->category($name), $this->sourceCategoryNames());
    }

    /** @return list<string> */
    private function sourceCategoryNames(): array
    {
        $data = $this->get('/products/categories');

        if (! is_array($data) || ! array_is_list($data) || array_filter($data, fn ($v) => ! is_string($v)) !== []) {
            throw new UnexpectedProviderResponseException('Category list was not a list of strings.');
        }

        return $data;
    }

    /**
     * One malformed product must not take the whole catalogue down: skip it and log it.
     *
     * @param  array<int, mixed>  $items
     * @return list<Product>
     */
    private function mapMany(array $items): array
    {
        $products = [];
        foreach ($items as $item) {
            try {
                $products[] = $this->mapper->product(is_array($item) ? $item : []);
            } catch (UnexpectedProviderResponseException $e) {
                $this->logger->warning('Skipping malformed product from Fake Store.', ['reason' => $e->getMessage()]);
            }
        }

        return $products;
    }

    /**
     * @return mixed Decoded JSON, or null when the resource does not exist / the body is empty.
     *
     * @throws ProviderUnavailableException
     */
    private function get(string $path): mixed
    {
        try {
            $response = $this->http
                ->baseUrl($this->baseUrl)
                ->acceptJson()
                ->withUserAgent('ProductCatalogue/1.0')
                ->connectTimeout(2)
                ->timeout($this->timeoutSeconds)
                ->retry(
                    $this->retries + 1,
                    $this->retrySleepMs,
                    fn (Throwable $e): bool => $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->serverError()),
                    throw: false,
                )
                ->get($path);
        } catch (ConnectionException $e) {
            $this->logger->warning('Fake Store unreachable.', ['path' => $path, 'reason' => $e->getMessage()]);
            throw new ProviderUnavailableException('The product source could not be reached.', 0, $e);
        }

        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');
            throw new ProviderRateLimitedException(is_numeric($retryAfter) ? (int) $retryAfter : null);
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            $this->logger->warning('Fake Store returned an error.', ['path' => $path, 'status' => $response->status()]);
            throw new ProviderUnavailableException("The product source responded with HTTP {$response->status()}.");
        }

        $body = trim($response->body());
        if ($body === '' || $body === 'null') {
            return null;
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedProviderResponseException('The product source returned invalid JSON.', 0, $e);
        }
    }
}
