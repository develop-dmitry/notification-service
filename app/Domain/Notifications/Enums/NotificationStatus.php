<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
