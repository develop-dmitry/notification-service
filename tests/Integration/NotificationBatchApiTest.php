<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Enums\Priority;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class NotificationBatchApiTest extends IntegrationTestCase
{
    #[Test]
    public function it_creates_batch_and_queues_notifications_per_priority(): void
    {
        $key = (string) Str::uuid();
        $payload = [
            'channel' => 'email',
            'priority' => 'high',
            'message' => 'Hello!',
            'recipients' => [
                ['id' => 'u1', 'address' => 'a@example.com'],
                ['id' => 'u2', 'address' => 'b@example.com'],
            ],
        ];

        $response = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/notifications', $payload);

        $response->assertStatus(202)
            ->assertJsonStructure(['data' => ['batch_id', 'accepted', 'channel', 'priority']]);

        $batch = NotificationBatch::query()->firstOrFail();
        $this->assertSame(Channel::Email, $batch->channel);
        $this->assertSame(Priority::High, $batch->priority);
        $this->assertSame($key, $batch->idempotency_key);

        $this->assertSame(2, Notification::query()->where('status', NotificationStatus::Queued->value)->count());
        $this->assertSame(2, $this->queueSize($this->queueHigh));
        $this->assertSame(0, $this->queueSize($this->queueLow));
    }

    #[Test]
    public function it_rejects_missing_idempotency_key(): void
    {
        $response = $this->postJson('/api/v1/notifications', [
            'channel' => 'sms',
            'priority' => 'low',
            'message' => 'x',
            'recipients' => [['id' => '1', 'address' => '+79990000000']],
        ]);

        $response->assertStatus(400)->assertJson(['error' => 'idempotency_key_required']);
    }

    #[Test]
    public function it_rejects_invalid_channel(): void
    {
        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/notifications', [
                'channel' => 'pigeon',
                'priority' => 'low',
                'message' => 'x',
                'recipients' => [['id' => '1', 'address' => 'a@b.c']],
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_rejects_empty_recipients(): void
    {
        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/notifications', [
                'channel' => 'email',
                'priority' => 'low',
                'message' => 'x',
                'recipients' => [],
            ]);

        $response->assertStatus(422);
    }
}
