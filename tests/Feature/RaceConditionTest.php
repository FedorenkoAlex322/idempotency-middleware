<?php

use FedorenkoAlex322\IdempotencyMiddleware\Exceptions\IdempotencyException;
use FedorenkoAlex322\IdempotencyMiddleware\IdempotencyMiddleware;
use FedorenkoAlex322\IdempotencyMiddleware\Storage\IdempotencyStorageInterface;
use FedorenkoAlex322\IdempotencyMiddleware\Tests\Stubs\ArrayStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

test('second request waits and gets cached result when lock is held', function () {
    // Create a storage that fails lock N times, then returns cached data
    $storage = new class () extends ArrayStorage {
        private int $lockAttempts = 0;

        public function lock(string $key, int $ttl): bool
        {
            $this->lockAttempts++;

            // First call from the "first request" succeeds
            if ($this->lockAttempts === 1) {
                return true;
            }

            // Subsequent calls fail (simulating held lock)
            return false;
        }

        public function get(string $key): ?array
        {
            // After a few poll attempts in waitForResult, return cached data
            static $getAttempts = 0;
            $getAttempts++;

            // First two gets: null (initial check + double-check after lock)
            // Third get: null (first poll in waitForResult from second request initial check)
            // Fourth get: return cached (poll succeeds in waitForResult)
            if ($getAttempts <= 3) {
                return parent::get($key);
            }

            return [
                'status' => 201,
                'headers' => ['content-type' => ['application/json']],
                'body' => '{"data":"created","id":"cached-id"}',
            ];
        }
    };

    $this->app->singleton(IdempotencyStorageInterface::class, fn () => $storage);

    Route::post('/test-race', function (Request $request) {
        return response()->json(['data' => 'created', 'id' => 'original-id'], 201);
    })->middleware(IdempotencyMiddleware::class);

    // First request succeeds and caches
    $first = $this->postJson('/test-race', [], [
        'Idempotency-Key' => 'race-key',
    ]);
    $first->assertStatus(201);
    $first->assertHeader('Idempotency-Key-Status', 'miss');

    // Second request hits waitForResult path, eventually gets cached response
    $second = $this->postJson('/test-race', [], [
        'Idempotency-Key' => 'race-key',
    ]);
    $second->assertStatus(201);
    $second->assertHeader('Idempotency-Key-Status', 'hit');
});

test('lock timeout returns 409 via idempotency exception', function () {
    // Use very short timeout so test runs fast
    config()->set('idempotency.lock_wait_timeout', 1);
    config()->set('idempotency.lock_wait_interval', 50);

    /** @var ArrayStorage $storage */
    $storage = $this->app->make(IdempotencyStorageInterface::class);

    Route::post('/test-timeout', function (Request $request) {
        return response()->json(['data' => 'ok'], 200);
    })->middleware(IdempotencyMiddleware::class);

    // Pre-compute the hash to simulate the lock on the correct key
    $middleware = new IdempotencyMiddleware($storage);
    $method = new ReflectionMethod($middleware, 'computeHash');
    $method->setAccessible(true);

    $request = Request::create(
        $this->app->make('url')->to('/test-timeout'),
        'POST',
    );
    $hash = $method->invoke($middleware, $request, 'timeout-key');

    // Simulate an existing lock held by another process
    $storage->simulateLock($hash);

    $this->withoutExceptionHandling();

    $this->expectException(IdempotencyException::class);
    $this->expectExceptionMessage('Idempotency lock wait timeout exceeded for key');

    $this->postJson('/test-timeout', [], [
        'Idempotency-Key' => 'timeout-key',
    ]);
});
