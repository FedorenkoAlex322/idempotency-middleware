<?php

namespace FedorenkoAlex322\IdempotencyMiddleware\Storage;

use Illuminate\Support\Facades\Redis;

class RedisStorage implements IdempotencyStorageInterface
{
    public function __construct(
        private string $connection = 'default',
        private string $prefix = 'idempotency:',
    ) {
    }

    /**
     * @return array{status: int, headers: array<string, mixed>, body: string}|null
     */
    public function get(string $key): ?array
    {
        /** @var \Redis $client */
        $client = Redis::connection($this->connection)->client();

        $result = $client->get($this->prefix . $key);

        // phpredis returns false on miss, predis returns null
        if ($result === null || $result === false) {
            return null;
        }

        /** @var array{status: int, headers: array<string, mixed>, body: string} */
        return json_decode((string) $result, true);
    }

    /**
     * @param array{status: int, headers: array<string, mixed>, body: string} $data
     */
    public function put(string $key, array $data, int $ttl): void
    {
        /** @var \Redis $client */
        $client = Redis::connection($this->connection)->client();

        $client->setex($this->prefix . $key, $ttl, json_encode($data));
    }

    public function lock(string $key, int $ttl): bool
    {
        /** @var \Redis $client */
        $client = Redis::connection($this->connection)->client();

        $result = $client->set($this->prefix . 'lock:' . $key, '1', ['NX', 'EX' => $ttl]);

        return $result === true;
    }

    public function unlock(string $key): void
    {
        /** @var \Redis $client */
        $client = Redis::connection($this->connection)->client();

        $client->del($this->prefix . 'lock:' . $key);
    }
}
