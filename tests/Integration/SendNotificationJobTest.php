<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Notifications\Contracts\NotificationProvider;
use App\Domain\Notifications\DataTransferObjects\ProviderResult;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Exceptions\TransientDeliveryException;
use App\Domain\Notifications\Providers\MockEmailProvider;
use App\Domain\Notifications\Services\NotificationProviderResolver;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationBatch;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class SendNotificationJobTest extends IntegrationTestCase
{
    #[Test]
    public function transient_failures_increment_attempts_and_throw_for_retry(): void
    {
        $batch = NotificationBatch::factory()->email()->create();
        $notification = Notification::factory()->forBatch($batch)->create([
            'recipient_address' => 'flaky@example.com',
            'published_at' => now(),
        ]);

        $job = new SendNotificationJob($notification->id);
        $resolver = resolve(NotificationProviderResolver::class);

        try {
            $job->handle($resolver);
            $this->fail('Expected TransientDeliveryException');
        } catch (TransientDeliveryException) {
            // expected
        }

        $notification->refresh();
        $this->assertSame(NotificationStatus::Queued, $notification->status);
        $this->assertSame(1, $notification->attempts_count);
        $this->assertNotNull($notification->last_error);
    }

    #[Test]
    public function transient_then_success_keeps_attempts_count(): void
    {
        $batch = NotificationBatch::factory()->email()->create();
        $notification = Notification::factory()->forBatch($batch)->create([
            'recipient_address' => 'ok@example.com',
            'published_at' => now(),
        ]);

        $provider = new class implements NotificationProvider
        {
            public int $calls = 0;

            public function send(Notification $notification): ProviderResult
            {
                $this->calls++;
                if ($this->calls < 3) {
                    return ProviderResult::transientFailure('transient #'.$this->calls);
                }

                return ProviderResult::accepted('msg-id-final');
            }
        };

        app()->bind(MockEmailProvider::class, fn (): NotificationProvider => $provider);

        $resolver = resolve(NotificationProviderResolver::class);
        $job = new SendNotificationJob($notification->id);

        for ($i = 0; $i < 2; $i++) {
            try {
                $job->handle($resolver);
            } catch (TransientDeliveryException) {
            }
        }
        $job->handle($resolver);

        $notification->refresh();
        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertSame(3, $notification->attempts_count);
        $this->assertSame('msg-id-final', $notification->provider_message_id);
        $this->assertSame(3, $notification->attempts()->count());
    }

    #[Test]
    public function failed_callback_marks_notification_as_failed(): void
    {
        $batch = NotificationBatch::factory()->email()->create();
        $notification = Notification::factory()->forBatch($batch)->create([
            'recipient_address' => 'flaky@example.com',
            'published_at' => now(),
            'attempts_count' => 5,
        ]);

        $job = new SendNotificationJob($notification->id);
        $job->failed(new RuntimeException('boom'));

        $notification->refresh();
        $this->assertSame(NotificationStatus::Failed, $notification->status);
        $this->assertSame('boom', $notification->last_error);
        $this->assertNotNull($notification->failed_at);
    }

    #[Test]
    public function redelivery_of_already_sent_notification_is_skipped(): void
    {
        $batch = NotificationBatch::factory()->email()->create();
        $notification = Notification::factory()->forBatch($batch)
            ->status(NotificationStatus::Sent)
            ->create([
                'recipient_address' => 'ok@example.com',
                'attempts_count' => 1,
                'sent_at' => now()->subSecond(),
                'provider_message_id' => 'pre-existing',
            ]);

        $provider = new class implements NotificationProvider
        {
            public int $calls = 0;

            public function send(Notification $notification): ProviderResult
            {
                $this->calls++;

                return ProviderResult::accepted('unexpected-call');
            }
        };
        app()->bind(MockEmailProvider::class, fn (): NotificationProvider => $provider);

        $job = new SendNotificationJob($notification->id);
        $job->handle(resolve(NotificationProviderResolver::class));

        $this->assertSame(0, $provider->calls, 'Provider must not be called for non-queued notifications');

        $notification->refresh();
        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertSame(1, $notification->attempts_count);
        $this->assertSame('pre-existing', $notification->provider_message_id);
        $this->assertSame(0, $notification->attempts()->count());
    }
}
