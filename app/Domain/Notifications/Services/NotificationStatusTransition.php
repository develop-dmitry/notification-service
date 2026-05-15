<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Enums\NotificationStatus;

class NotificationStatusTransition
{
    /**
     * @var array<string, list<NotificationStatus>>
     */
    private const array ALLOWED = [
        'queued' => [NotificationStatus::Sent, NotificationStatus::Failed],
        'sent' => [NotificationStatus::Delivered, NotificationStatus::Failed],
        'delivered' => [],
        'failed' => [],
    ];

    public function canApply(NotificationStatus $from, NotificationStatus $to): bool
    {
        return in_array($to, self::ALLOWED[$from->value], true);
    }
}
