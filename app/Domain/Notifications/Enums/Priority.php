<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

enum Priority: string
{
    case High = 'high';
    case Low = 'low';

    public function queue(): string
    {
        return match ($this) {
            self::High => (string) config('notifications.queues.high', 'notifications.high'),
            self::Low => (string) config('notifications.queues.low', 'notifications.low'),
        };
    }
}
