<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Notifications\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;

class WorkerProcessingTest extends IntegrationTestCase
{
    #[Test]
    public function worker_sends_notification_and_marks_it_sent(): void
    {
        $batch = NotificationBatch::factory()->email()->create();
        $notification = Notification::factory()->forBatch($batch)->create([
            'recipient_address' => 'ok@example.com',
            'published_at' => now(),
        ]);

        Queue::connection('rabbitmq')->pushOn($this->queueLow, new SendNotificationJob($notification->id));

        $this->runWorkerOnce($this->queueLow);

        $notification->refresh();
        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertNotNull($notification->sent_at);
        $this->assertSame(1, $notification->attempts_count);
        $this->assertNotNull($notification->provider_message_id);
        $this->assertSame(1, $notification->attempts()->count());
    }

    #[Test]
    public function worker_marks_permanent_failure_without_retry(): void
    {
        $batch = NotificationBatch::factory()->email()->create();
        $notification = Notification::factory()->forBatch($batch)->create([
            'recipient_address' => 'fail@example.com',
            'published_at' => now(),
        ]);

        Queue::connection('rabbitmq')->pushOn($this->queueLow, new SendNotificationJob($notification->id));
        $this->runWorkerOnce($this->queueLow);

        $notification->refresh();
        $this->assertSame(NotificationStatus::Failed, $notification->status);
        $this->assertSame(1, $notification->attempts_count);
        $this->assertNotNull($notification->failed_at);
        $this->assertNotNull($notification->last_error);
        $this->assertSame(0, $this->queueSize($this->queueLow));
    }

    #[Test]
    public function provider_callback_marks_notification_as_delivered(): void
    {
        $batch = NotificationBatch::factory()->email()->create();
        $notification = Notification::factory()->forBatch($batch)
            ->status(NotificationStatus::Sent)
            ->create(['sent_at' => now()->subSecond()]);

        $response = $this->postJson('/api/v1/_internal/provider-callback', [
            'notification_id' => $notification->id,
            'status' => 'delivered',
        ]);

        $response->assertStatus(200);

        $notification->refresh();
        $this->assertSame(NotificationStatus::Delivered, $notification->status);
        $this->assertNotNull($notification->delivered_at);
    }

    #[Test]
    public function high_priority_jobs_are_dispatched_to_high_queue(): void
    {
        $batch = NotificationBatch::factory()->email()->highPriority()->create();
        $notification = Notification::factory()->forBatch($batch)->create([
            'recipient_address' => 'ok@example.com',
            'published_at' => now(),
        ]);

        Queue::connection('rabbitmq')->pushOn($this->queueHigh, new SendNotificationJob($notification->id));

        $this->assertSame(1, $this->queueSize($this->queueHigh));
        $this->assertSame(0, $this->queueSize($this->queueLow));

        $this->runWorkerOnce($this->queueHigh);

        $notification->refresh();
        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertSame(0, $this->queueSize($this->queueHigh));
    }
}
