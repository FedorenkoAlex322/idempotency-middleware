<?php

namespace FedorenkoAlex322\IdempotencyMiddleware\Storage;

interface IdempotencyStorageInterface
{
    /**
     * @return array{status: int, headers: array<string, mixed>, body: string}|null
     */
    public function get(string $key): ?array;

    /**
     * @param array{status: int, headers: array<string, mixed>, body: string} $data
     */
    public function put(string $key, array $data, int $ttl): void;

    public function lock(string $key, int $ttl): bool;

    public function unlock(string $key): void;
}
