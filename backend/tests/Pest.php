<?php

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Tests must never reach the real Fake Store API.
pest()->extend(TestCase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature', 'Unit', 'Contract');
