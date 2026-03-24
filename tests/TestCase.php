<?php

namespace FedorenkoAlex322\IdempotencyMiddleware\Tests;

use FedorenkoAlex322\IdempotencyMiddleware\IdempotencyServiceProvider;
use FedorenkoAlex322\IdempotencyMiddleware\Storage\IdempotencyStorageInterface;
use FedorenkoAlex322\IdempotencyMiddleware\Tests\Stubs\ArrayStorage;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            IdempotencyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('idempotency.ttl', 60);
        $app['config']->set('idempotency.lock_wait_timeout', 2);
        $app['config']->set('idempotency.lock_wait_interval', 50);

        $app->singleton(IdempotencyStorageInterface::class, function () {
            return new ArrayStorage();
        });
    }
}
