<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\NotificationBatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class IdempotencyTest extends IntegrationTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'channel' => 'email',
            'priority' => 'low',
            'message' => 'Hello',
            'recipients' => [['id' => 'u1', 'address' => 'ok@example.com']],
        ];
    }

    #[Test]
    public function repeated_request_with_same_key_and_body_returns_same_batch(): void
    {
        $key = (string) Str::uuid();

        $first = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/notifications', $this->payload())
            ->assertStatus(202);

        $second = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/notifications', $this->payload())
            ->assertStatus(202);

        $this->assertSame(
            $first->json('data.batch_id'),
            $second->json('data.batch_id'),
        );
        $this->assertSame(1, NotificationBatch::query()->count());
        $this->assertSame(1, $this->queueSize($this->queueLow));
    }

    #[Test]
    public function repeated_request_with_same_key_and_different_body_returns_409(): void
    {
        $key = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/notifications', $this->payload())
            ->assertStatus(202);

        $modified = $this->payload();
        $modified['message'] = 'Different message';

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/notifications', $modified)
            ->assertStatus(409)
            ->assertJson(['error' => 'idempotency_conflict']);
    }

    #[Test]
    public function expired_idempotency_key_is_treated_as_new_request(): void
    {
        $key = (string) Str::uuid();

        $first = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/notifications', $this->payload())
            ->assertStatus(202);

        // Симулируем истечение TTL: вычищаем запись из кеша.
        Cache::store(config('notifications.idempotency.store'))
            ->forget(config('notifications.idempotency.key_prefix').$key);

        $second = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/notifications', $this->payload())
            ->assertStatus(202);

        $this->assertNotSame(
            $first->json('data.batch_id'),
            $second->json('data.batch_id'),
        );
        $this->assertSame(2, NotificationBatch::query()->count());
        $this->assertSame(2, $this->queueSize($this->queueLow));
    }
}
