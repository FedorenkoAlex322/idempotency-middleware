<?php

use FedorenkoAlex322\IdempotencyMiddleware\IdempotencyMiddleware;
use FedorenkoAlex322\IdempotencyMiddleware\Tests\Stubs\ArrayStorage;
use Illuminate\Http\Request;

function createMiddlewareForHash(): IdempotencyMiddleware
{
    return new IdempotencyMiddleware(new ArrayStorage());
}

function invokeComputeHash(IdempotencyMiddleware $middleware, Request $request, string $key): string
{
    $method = new ReflectionMethod($middleware, 'computeHash');
    $method->setAccessible(true);

    return $method->invoke($middleware, $request, $key);
}

test('identical inputs produce identical hash', function () {
    $middleware = createMiddlewareForHash();

    $request1 = Request::create('/api/orders', 'POST', ['amount' => 100]);
    $request2 = Request::create('/api/orders', 'POST', ['amount' => 100]);

    $hash1 = invokeComputeHash($middleware, $request1, 'key-123');
    $hash2 = invokeComputeHash($middleware, $request2, 'key-123');

    expect($hash1)->toBe($hash2);
});

test('different method produces different hash', function () {
    $middleware = createMiddlewareForHash();

    $requestPost = Request::create('/api/orders', 'POST');
    $requestPut = Request::create('/api/orders', 'PUT');

    $hashPost = invokeComputeHash($middleware, $requestPost, 'key-123');
    $hashPut = invokeComputeHash($middleware, $requestPut, 'key-123');

    expect($hashPost)->not->toBe($hashPut);
});

test('different url produces different hash', function () {
    $middleware = createMiddlewareForHash();

    $request1 = Request::create('/api/orders', 'POST');
    $request2 = Request::create('/api/payments', 'POST');

    $hash1 = invokeComputeHash($middleware, $request1, 'key-123');
    $hash2 = invokeComputeHash($middleware, $request2, 'key-123');

    expect($hash1)->not->toBe($hash2);
});

test('different idempotency key produces different hash', function () {
    $middleware = createMiddlewareForHash();

    $request = Request::create('/api/orders', 'POST');

    $hash1 = invokeComputeHash($middleware, $request, 'key-aaa');
    $hash2 = invokeComputeHash($middleware, $request, 'key-bbb');

    expect($hash1)->not->toBe($hash2);
});

test('hash is a valid sha256 hex string', function () {
    $middleware = createMiddlewareForHash();

    $request = Request::create('/api/orders', 'POST');
    $hash = invokeComputeHash($middleware, $request, 'key-123');

    expect($hash)
        ->toHaveLength(64)
        ->toMatch('/^[a-f0-9]{64}$/');
});
