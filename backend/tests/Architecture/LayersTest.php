<?php

/*
 * Architecture tests: the layer rules from AGENTS.md, enforced automatically.
 */
arch('controllers stay thin: no HTTP client, cache, database or integrations')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Http\Client',
        'Illuminate\Support\Facades\Http',
        'Illuminate\Support\Facades\Cache',
        'Illuminate\Support\Facades\DB',
        'App\Infrastructure',
    ]);

arch('services depend on contracts, never on concrete integrations or facades')
    ->expect('App\Services')
    ->not->toUse(['App\Infrastructure', 'Illuminate\Support\Facades', 'Illuminate\Http']);

arch('DTOs are immutable and framework-free')
    ->expect('App\DTOs')
    ->toBeReadonly()
    ->not->toUse('Illuminate');

arch('contracts are interfaces')
    ->expect('App\Contracts')
    ->toBeInterfaces();

arch('infrastructure implements the product contract')
    ->expect('App\Infrastructure\Products')
    ->toImplement('App\Contracts\ProductProviderInterface')
    ->ignoring('App\Infrastructure\Products\FakeStoreMapper');

arch('production code does not depend on tests')
    ->expect('App')
    ->not->toUse('Tests');

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'die', 'exit'])
    ->not->toBeUsed();
