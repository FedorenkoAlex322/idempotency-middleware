<?php

use FedorenkoAlex322\IdempotencyMiddleware\IdempotencyMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::post('/test-endpoint', function (Request $request) {
        return response()->json(['data' => 'created', 'id' => uniqid()], 201);
    })->middleware(IdempotencyMiddleware::class);

    Route::post('/test-endpoint-1', function (Request $request) {
        return response()->json(['endpoint' => 'one', 'id' => uniqid()], 200);
    })->middleware(IdempotencyMiddleware::class);

    Route::post('/test-endpoint-2', function (Request $request) {
        return response()->json(['endpoint' => 'two', 'id' => uniqid()], 200);
    })->middleware(IdempotencyMiddleware::class);

    Route::post('/test-custom-header', function (Request $request) {
        return response()->json(['ok' => true], 200)
            ->header('X-Custom-Header', 'custom-value');
    })->middleware(IdempotencyMiddleware::class);

    Route::post('/test-error', function (Request $request) {
        return response()->json(['error' => 'server error'], 500);
    })->middleware(IdempotencyMiddleware::class);
});

test('request without idempotency key passes through', function () {
    $response = $this->postJson('/test-endpoint', ['amount' => 100]);

    $response->assertStatus(201);
    $response->assertHeaderMissing('Idempotency-Key-Status');
});

test('first request with key returns miss status', function () {
    $response = $this->postJson('/test-endpoint', ['amount' => 100], [
        'Idempotency-Key' => 'unique-key-001',
    ]);

    $response->assertStatus(201);
    $response->assertHeader('Idempotency-Key-Status', 'miss');
});

test('repeated request returns cached response with hit status', function () {
    $first = $this->postJson('/test-endpoint', ['amount' => 100], [
        'Idempotency-Key' => 'unique-key-002',
    ]);

    $second = $this->postJson('/test-endpoint', ['amount' => 100], [
        'Idempotency-Key' => 'unique-key-002',
    ]);

    $first->assertHeader('Idempotency-Key-Status', 'miss');
    $second->assertHeader('Idempotency-Key-Status', 'hit');

    expect($second->getStatusCode())->toBe($first->getStatusCode());
    expect($second->json('data'))->toBe($first->json('data'));
    expect($second->json('id'))->toBe($first->json('id'));
});

test('different endpoints with same key do not conflict', function () {
    $response1 = $this->postJson('/test-endpoint-1', [], [
        'Idempotency-Key' => 'shared-key',
    ]);

    $response2 = $this->postJson('/test-endpoint-2', [], [
        'Idempotency-Key' => 'shared-key',
    ]);

    $response1->assertHeader('Idempotency-Key-Status', 'miss');
    $response2->assertHeader('Idempotency-Key-Status', 'miss');

    expect($response1->json('endpoint'))->toBe('one');
    expect($response2->json('endpoint'))->toBe('two');
});

test('different keys for same endpoint are independent', function () {
    $response1 = $this->postJson('/test-endpoint', [], [
        'Idempotency-Key' => 'key-alpha',
    ]);

    $response2 = $this->postJson('/test-endpoint', [], [
        'Idempotency-Key' => 'key-beta',
    ]);

    $response1->assertHeader('Idempotency-Key-Status', 'miss');
    $response2->assertHeader('Idempotency-Key-Status', 'miss');

    expect($response1->json('id'))->not->toBe($response2->json('id'));
});

test('cached response preserves status code', function () {
    $first = $this->postJson('/test-endpoint', [], [
        'Idempotency-Key' => 'preserve-status',
    ]);

    $second = $this->postJson('/test-endpoint', [], [
        'Idempotency-Key' => 'preserve-status',
    ]);

    expect($first->getStatusCode())->toBe(201);
    expect($second->getStatusCode())->toBe(201);
});

test('cached response preserves headers', function () {
    $first = $this->postJson('/test-custom-header', [], [
        'Idempotency-Key' => 'preserve-headers',
    ]);

    $second = $this->postJson('/test-custom-header', [], [
        'Idempotency-Key' => 'preserve-headers',
    ]);

    $first->assertHeader('X-Custom-Header', 'custom-value');
    $second->assertHeader('X-Custom-Header', 'custom-value');
});

test('cached response preserves body', function () {
    $first = $this->postJson('/test-endpoint', ['amount' => 500], [
        'Idempotency-Key' => 'preserve-body',
    ]);

    $second = $this->postJson('/test-endpoint', ['amount' => 500], [
        'Idempotency-Key' => 'preserve-body',
    ]);

    expect($second->getContent())->toBe($first->getContent());
});

test('error responses are cached', function () {
    $first = $this->postJson('/test-error', [], [
        'Idempotency-Key' => 'error-key',
    ]);

    $second = $this->postJson('/test-error', [], [
        'Idempotency-Key' => 'error-key',
    ]);

    expect($first->getStatusCode())->toBe(500);
    $first->assertHeader('Idempotency-Key-Status', 'miss');

    expect($second->getStatusCode())->toBe(500);
    $second->assertHeader('Idempotency-Key-Status', 'hit');

    expect($second->json('error'))->toBe('server error');
});
