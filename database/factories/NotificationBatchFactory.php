<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\Priority;
use App\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<NotificationBatch>
 */
class NotificationBatchFactory extends Factory
{
    protected $model = NotificationBatch::class;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'channel' => fake()->randomElement(Channel::cases())->value,
            'priority' => Priority::Low->value,
            'message' => fake()->sentence(),
            'idempotency_key' => null,
        ];
    }

    public function highPriority(): self
    {
        return $this->state(['priority' => Priority::High->value]);
    }

    public function sms(): self
    {
        return $this->state(['channel' => Channel::Sms->value]);
    }

    public function email(): self
    {
        return $this->state(['channel' => Channel::Email->value]);
    }
}
