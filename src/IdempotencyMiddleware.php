<?php

namespace FedorenkoAlex322\IdempotencyMiddleware;

use Closure;
use FedorenkoAlex322\IdempotencyMiddleware\Exceptions\IdempotencyException;
use FedorenkoAlex322\IdempotencyMiddleware\Storage\IdempotencyStorageInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IdempotencyMiddleware
{
    public function __construct(
        private IdempotencyStorageInterface $storage,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey === null) {
            return $next($request);
        }

        $hash = $this->computeHash($request, $idempotencyKey);

        $cached = $this->storage->get($hash);

        if ($cached !== null) {
            return $this->buildResponse($cached, 'hit');
        }

        if ($this->storage->lock($hash, (int) config('idempotency.lock_wait_timeout'))) {
            try {
                // Double-check after acquiring lock
                $cached = $this->storage->get($hash);

                if ($cached !== null) {
                    return $this->buildResponse($cached, 'hit');
                }

                $response = $next($request);

                // Skip caching for streamed/binary responses
                if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
                    return $response;
                }

                $this->storage->put(
                    $hash,
                    $this->serializeResponse($response),
                    (int) config('idempotency.ttl'),
                );

                $response->headers->set('Idempotency-Key-Status', 'miss');

                return $response;
            } finally {
                $this->storage->unlock($hash);
            }
        }

        return $this->waitForResult($hash);
    }

    private function computeHash(Request $request, string $idempotencyKey): string
    {
        return hash('sha256', $request->method() . '|' . $request->fullUrl() . '|' . $idempotencyKey);
    }

    /**
     * @return array{status: int, headers: array<string, mixed>, body: string}
     */
    private function serializeResponse(Response $response): array
    {
        return [
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'body' => (string) $response->getContent(),
        ];
    }

    /**
     * @param array{status: int, headers: array<string, mixed>, body: string} $data
     */
    private function buildResponse(array $data, string $status): Response
    {
        $response = new IlluminateResponse($data['body'], $data['status'], $data['headers']);
        $response->headers->set('Idempotency-Key-Status', $status);

        return $response;
    }

    private function waitForResult(string $hash): Response
    {
        $timeout = (int) config('idempotency.lock_wait_timeout');
        $interval = (int) config('idempotency.lock_wait_interval');
        $startTime = microtime(true);

        while (microtime(true) - $startTime < $timeout) {
            usleep($interval * 1000);

            $cached = $this->storage->get($hash);

            if ($cached !== null) {
                return $this->buildResponse($cached, 'hit');
            }
        }

        throw IdempotencyException::lockTimeout($hash);
    }
}
