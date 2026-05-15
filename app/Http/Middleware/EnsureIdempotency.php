<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware\Idempotency\IdempotencyRecord;
use App\Http\Middleware\Idempotency\IdempotencyStore;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function __construct(private readonly IdempotencyStore $store) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if (($error = $this->validateKey($key)) instanceof JsonResponse) {
            return $error;
        }
        /** @var string $key */
        $bodyHash = hash('sha256', $request->getContent());

        if (($existing = $this->store->find($key)) instanceof IdempotencyRecord) {
            return $this->respondForExisting($existing, $bodyHash);
        }

        if (! $this->store->tryAcquireLock($key, $bodyHash)) {
            return $this->inProgressResponse();
        }

        $response = $next($request);
        $this->persistResponse($key, $bodyHash, $response);

        return $response;
    }

    private function validateKey(?string $key): ?JsonResponse
    {
        if ($key === null || $key === '') {
            return $this->error('idempotency_key_required', 'Header Idempotency-Key is required for this endpoint.', 400);
        }

        if (! Str::isUuid($key)) {
            return $this->error('idempotency_key_invalid', 'Idempotency-Key must be a valid UUID.', 400);
        }

        return null;
    }

    private function respondForExisting(IdempotencyRecord $existing, string $bodyHash): JsonResponse
    {
        if (! $existing->matches($bodyHash)) {
            return $this->error('idempotency_conflict', 'Idempotency-Key was reused with a different request body.', 409);
        }

        if ($existing->isCompleted()) {
            return response()->json($existing->body, $existing->status ?? 200);
        }

        return $this->inProgressResponse();
    }

    private function persistResponse(string $key, string $bodyHash, Response $response): void
    {
        if ($response->getStatusCode() >= 400) {
            $this->store->release($key);

            return;
        }

        $this->store->saveResponse(
            $key,
            IdempotencyRecord::completed($bodyHash, $response->getStatusCode(), $this->decodeBody($response)),
        );
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function decodeBody(Response $response): ?array
    {
        $content = $response->getContent();
        if ($content === false || $content === '') {
            return null;
        }

        $decoded = json_decode($content, associative: true);

        return is_array($decoded) ? $decoded : null;
    }

    private function inProgressResponse(): JsonResponse
    {
        return $this->error(
            'idempotency_in_progress',
            'Another request with the same Idempotency-Key is still being processed.',
            409,
        );
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $code, 'message' => $message], $status);
    }
}
