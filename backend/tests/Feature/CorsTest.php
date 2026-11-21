<?php

it('does not grant cross-origin access by default', function () {
    $this->getJson('/api/v1/health', ['Origin' => 'https://evil.example'])
        ->assertOk()
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});
