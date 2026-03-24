<?php

namespace FedorenkoAlex322\IdempotencyMiddleware\Tests\Stubs;

use FedorenkoAlex322\IdempotencyMiddleware\Storage\IdempotencyStorageInterface;

class ArrayStorage implements IdempotencyStorageInterface
{
    private array $store = [];

    private array $locks = [];

    /**
     * @return array{status: int, headers: array<string, mixed>, body: string}|null
     */
    public function get(string $key): ?array
    {
        return $this->store[$key] ?? null;
    }

    /**
     * @param array{status: int, headers: array<string, mixed>, body: string} $data
     */
    public function put(string $key, array $data, int $ttl): void
    {
        $this->store[$key] = $data;
    }

    public function lock(string $key, int $ttl): bool
    {
        if (isset($this->locks[$key])) {
            return false;
        }

        $this->locks[$key] = true;

        return true;
    }

    public function unlock(string $key): void
    {
        unset($this->locks[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->store[$key]);
    }

    public function flush(): void
    {
        $this->store = [];
        $this->locks = [];
    }

    public function simulateLock(string $key): void
    {
        $this->locks[$key] = true;
    }
}
