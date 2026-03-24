<?php

namespace FedorenkoAlex322\IdempotencyMiddleware;

use FedorenkoAlex322\IdempotencyMiddleware\Storage\IdempotencyStorageInterface;
use FedorenkoAlex322\IdempotencyMiddleware\Storage\RedisStorage;
use Illuminate\Support\ServiceProvider;

class IdempotencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/idempotency.php', 'idempotency');

        $this->app->singleton(IdempotencyStorageInterface::class, function ($app): RedisStorage {
            /** @var array{redis_connection?: string, prefix?: string} $config */
            $config = $app['config']['idempotency'];

            return new RedisStorage(
                connection: $config['redis_connection'] ?? 'default',
                prefix: $config['prefix'] ?? 'idempotency:',
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/idempotency.php' => config_path('idempotency.php'),
            ], 'idempotency-config');
        }
    }
}
