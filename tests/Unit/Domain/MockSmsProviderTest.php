<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Notifications\Enums\ProviderResultStatus;
use App\Domain\Notifications\Providers\MockSmsProvider;
use App\Models\Notification;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MockSmsProviderTest extends TestCase
{
    private function notificationWithAddress(string $address): Notification
    {
        $notification = new Notification;
        $notification->forceFill(['recipient_address' => $address]);

        return $notification;
    }

    #[Test]
    public function fail_addresses_return_permanent_failure(): void
    {
        $provider = new MockSmsProvider(0.0, 0);
        $result = $provider->send($this->notificationWithAddress('fail-1@example.com'));
        $this->assertSame(ProviderResultStatus::PermanentFailure, $result->status);
    }

    #[Test]
    public function flaky_addresses_return_transient_failure(): void
    {
        $provider = new MockSmsProvider(0.0, 0);
        $result = $provider->send($this->notificationWithAddress('+79990000flaky'));
        $this->assertSame(ProviderResultStatus::TransientFailure, $result->status);
    }

    #[Test]
    public function normal_address_returns_accepted_with_provider_id(): void
    {
        $provider = new MockSmsProvider(0.0, 0);
        $result = $provider->send($this->notificationWithAddress('+79990000000'));
        $this->assertSame(ProviderResultStatus::Accepted, $result->status);
        $this->assertNotNull($result->providerMessageId);
    }
}
