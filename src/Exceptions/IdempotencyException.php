<?php

namespace FedorenkoAlex322\IdempotencyMiddleware\Exceptions;

use RuntimeException;

class IdempotencyException extends RuntimeException
{
    public static function lockTimeout(string $key): self
    {
        return new self("Idempotency lock wait timeout exceeded for key: {$key}");
    }
}
