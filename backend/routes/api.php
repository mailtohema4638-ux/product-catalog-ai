<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\FeaturedProductsController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);

    Route::middleware(['throttle:catalogue-read', 'cache.public:60'])->group(function (): void {
        Route::get('/featured', FeaturedProductsController::class);
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{slug}', [CategoryController::class, 'show'])->where('slug', '[a-z0-9-]{1,64}');
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'show'])->where('id', '[A-Za-z0-9_-]{1,64}');
    });
});
