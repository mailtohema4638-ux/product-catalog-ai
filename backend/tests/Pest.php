<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Tests must never reach the real Fake Store API.
pest()->extend(TestCase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature', 'Unit', 'Contract');

// Anything that touches the enquiries table starts from a clean database.
pest()->use(RefreshDatabase::class)->in('Feature');
