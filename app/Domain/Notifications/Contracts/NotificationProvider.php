<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\DataTransferObjects\ProviderResult;
use App\Models\Notification;

interface NotificationProvider
{
    public function send(Notification $notification): ProviderResult;
}
