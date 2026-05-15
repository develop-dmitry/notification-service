<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Providers;

use App\Domain\Notifications\Contracts\NotificationProvider;
use App\Domain\Notifications\DataTransferObjects\ProviderResult;
use App\Models\Notification;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

class MockSmsProvider implements NotificationProvider
{
    public function __construct(
        private readonly float $failureRate,
        private readonly int $latencyMs,
    ) {}

    public function send(Notification $notification): ProviderResult
    {
        $address = $notification->recipient_address;

        $latency = $this->latencyMs;
        if (Str::contains($address, 'slow', ignoreCase: true)) {
            $latency = max($latency, 1000);
        }
        if ($latency > 0) {
            Sleep::usleep($latency * 1000);
        }

        if (Str::contains($address, 'fail', ignoreCase: true)) {
            return ProviderResult::permanentFailure("Invalid recipient: {$address}");
        }

        if (Str::contains($address, 'flaky', ignoreCase: true)) {
            return ProviderResult::transientFailure('Transient SMS gateway error');
        }

        if ($this->failureRate > 0 && mt_rand(0, 9999) / 10000 < $this->failureRate) {
            return ProviderResult::transientFailure('Random transient SMS gateway error');
        }

        return ProviderResult::accepted('sms_'.Str::uuid()->toString());
    }
}
