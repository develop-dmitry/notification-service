<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Notifications\Enums\ProviderResultStatus;
use App\Models\Notification;
use App\Models\NotificationAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<NotificationAttempt>
 */
class NotificationAttemptFactory extends Factory
{
    protected $model = NotificationAttempt::class;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'notification_id' => Notification::factory(),
            'attempt_no' => 1,
            'result' => ProviderResultStatus::Accepted->value,
            'error' => null,
            'created_at' => now(),
        ];
    }
}
