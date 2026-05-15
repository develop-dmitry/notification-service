<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Notifications\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\NotificationBatch;
use PHPUnit\Framework\Attributes\Test;

class SubscriberNotificationsTest extends IntegrationTestCase
{
    #[Test]
    public function returns_subscriber_notifications_with_filters(): void
    {
        $batchEmail = NotificationBatch::factory()->email()->create();
        $batchSms = NotificationBatch::factory()->sms()->create();

        Notification::factory()->forBatch($batchEmail)->create([
            'recipient_id' => 'user-42',
            'status' => NotificationStatus::Delivered->value,
            'created_at' => now()->subMinutes(3),
        ]);
        Notification::factory()->forBatch($batchEmail)->create([
            'recipient_id' => 'user-42',
            'status' => NotificationStatus::Failed->value,
            'created_at' => now()->subMinutes(2),
        ]);
        Notification::factory()->forBatch($batchSms)->create([
            'recipient_id' => 'user-42',
            'status' => NotificationStatus::Sent->value,
            'created_at' => now()->subMinute(),
        ]);
        Notification::factory()->forBatch($batchEmail)->create([
            'recipient_id' => 'other-user',
        ]);

        $response = $this->getJson('/api/v1/subscribers/user-42/notifications');
        $response->assertStatus(200)->assertJsonCount(3, 'data');

        $statuses = array_column($response->json('data'), 'status');
        $this->assertSame(['sent', 'failed', 'delivered'], $statuses);

        $this->getJson('/api/v1/subscribers/user-42/notifications?channel=email')
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/subscribers/user-42/notifications?status=delivered')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'delivered');
    }

    #[Test]
    public function paginates_results_with_page_and_per_page(): void
    {
        $batch = NotificationBatch::factory()->email()->create();
        Notification::factory()->forBatch($batch)->count(5)->create(['recipient_id' => 'u-pg']);

        $first = $this->getJson('/api/v1/subscribers/u-pg/notifications?per_page=2&page=1');
        $first->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);

        $second = $this->getJson('/api/v1/subscribers/u-pg/notifications?per_page=2&page=2');
        $second->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2);

        $this->assertNotSame(
            $first->json('data.0.id'),
            $second->json('data.0.id'),
        );

        $this->getJson('/api/v1/subscribers/u-pg/notifications?per_page=2&page=3')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 3);
    }

    #[Test]
    public function rejects_invalid_filters(): void
    {
        $this->getJson('/api/v1/subscribers/u/notifications?status=unknown')->assertStatus(422);
        $this->getJson('/api/v1/subscribers/u/notifications?channel=pigeon')->assertStatus(422);
        $this->getJson('/api/v1/subscribers/u/notifications?per_page=0')->assertStatus(422);
        $this->getJson('/api/v1/subscribers/u/notifications?per_page=999')->assertStatus(422);
        $this->getJson('/api/v1/subscribers/u/notifications?page=0')->assertStatus(422);
    }
}
