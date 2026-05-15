<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\NotificationProvider;
use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Providers\MockEmailProvider;
use App\Domain\Notifications\Providers\MockSmsProvider;
use Illuminate\Contracts\Container\Container;

class NotificationProviderResolver
{
    public function __construct(private readonly Container $container) {}

    public function for(Channel $channel): NotificationProvider
    {
        return match ($channel) {
            Channel::Sms => $this->container->make(MockSmsProvider::class),
            Channel::Email => $this->container->make(MockEmailProvider::class),
        };
    }
}
