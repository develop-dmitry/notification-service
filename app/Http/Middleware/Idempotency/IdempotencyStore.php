<?php

declare(strict_types=1);

namespace App\Http\Middleware\Idempotency;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class IdempotencyStore
{
    public function __construct(
        private readonly string $storeName,
        private readonly string $keyPrefix,
        private readonly int $ttlSeconds,
        private readonly int $lockTtlSeconds,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            storeName: (string) config('notifications.idempotency.store', 'redis'),
            keyPrefix: (string) config('notifications.idempotency.key_prefix', 'idem:'),
            ttlSeconds: (int) config('notifications.idempotency.ttl_seconds', 86400),
            lockTtlSeconds: (int) config('notifications.idempotency.lock_ttl_seconds', 300),
        );
    }

    public function find(string $key): ?IdempotencyRecord
    {
        $raw = $this->cache()->get($this->key($key));

        return is_array($raw) ? IdempotencyRecord::fromArray($raw) : null;
    }

    public function tryAcquireLock(string $key, string $bodyHash): bool
    {
        return (bool) $this->cache()->add(
            $this->key($key),
            IdempotencyRecord::inProgress($bodyHash)->toArray(),
            $this->lockTtlSeconds,
        );
    }

    public function saveResponse(string $key, IdempotencyRecord $record): void
    {
        $this->cache()->put($this->key($key), $record->toArray(), $this->ttlSeconds);
    }

    public function release(string $key): void
    {
        $this->cache()->forget($this->key($key));
    }

    private function cache(): Repository
    {
        return Cache::store($this->storeName);
    }

    private function key(string $key): string
    {
        return $this->keyPrefix.$key;
    }
}
