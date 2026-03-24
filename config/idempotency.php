<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Response TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long to cache idempotent responses. Default: 24 hours.
    |
    */
    'ttl' => (int) env('IDEMPOTENCY_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Redis Connection
    |--------------------------------------------------------------------------
    |
    | Redis connection name from config/database.php.
    |
    */
    'redis_connection' => env('IDEMPOTENCY_REDIS_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for all Redis keys used by this package.
    |
    */
    'prefix' => env('IDEMPOTENCY_PREFIX', 'idempotency:'),

    /*
    |--------------------------------------------------------------------------
    | Lock Wait Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Maximum time to wait for a concurrent request to complete.
    |
    */
    'lock_wait_timeout' => (int) env('IDEMPOTENCY_LOCK_WAIT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Lock Wait Interval (milliseconds)
    |--------------------------------------------------------------------------
    |
    | Polling interval while waiting for a concurrent request.
    |
    */
    'lock_wait_interval' => (int) env('IDEMPOTENCY_LOCK_WAIT_INTERVAL', 100),
];
