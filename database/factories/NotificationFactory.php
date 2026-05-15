<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Enums\Priority;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        $channel = fake()->randomElement(Channel::cases());

        return [
            'batch_id' => NotificationBatch::factory(),
            'recipient_id' => (string) fake()->numberBetween(1, 100000),
            'recipient_address' => $channel === Channel::Sms
                ? fake()->e164PhoneNumber()
                : fake()->safeEmail(),
            'channel' => $channel->value,
            'priority' => Priority::Low->value,
            'status' => NotificationStatus::Queued->value,
            'attempts_count' => 0,
            'last_error' => null,
            'provider_message_id' => null,
            'published_at' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'failed_at' => null,
        ];
    }

    public function forBatch(NotificationBatch $batch): self
    {
        return $this->state(fn (): array => [
            'batch_id' => $batch->id,
            'channel' => $batch->channel->value,
            'priority' => $batch->priority->value,
        ]);
    }

    public function status(NotificationStatus $status): self
    {
        return $this->state(['status' => $status->value]);
    }
}
