<?php

it('rejects unauthenticated requests to POST /api/macro-logs', function () {
    $this->postJson('/api/macro-logs', [])->assertUnauthorized();
});

it('rejects unauthenticated requests to PUT /api/macro-logs/{id}', function () {
    $this->putJson('/api/macro-logs/1', [])->assertUnauthorized();
});

it('rejects unauthenticated requests to DELETE /api/macro-logs/{id}', function () {
    $this->deleteJson('/api/macro-logs/1')->assertUnauthorized();
});

it('rejects unauthenticated requests to GET /api/daily-summary', function () {
    $this->getJson('/api/daily-summary')->assertUnauthorized();
});

it('rejects unauthenticated requests to GET /api/weekly-summary', function () {
    $this->getJson('/api/weekly-summary')->assertUnauthorized();
});
