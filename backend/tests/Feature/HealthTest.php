<?php

it('reports ok on the health endpoint', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['data' => ['status' => 'ok']]);
});
