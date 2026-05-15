<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Services\NotificationStatusTransition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NotificationStatusTransitionTest extends TestCase
{
    /**
     * @return iterable<array{NotificationStatus, NotificationStatus, bool}>
     */
    public static function transitions(): iterable
    {
        yield 'queued -> sent' => [NotificationStatus::Queued, NotificationStatus::Sent, true];
        yield 'queued -> failed' => [NotificationStatus::Queued, NotificationStatus::Failed, true];
        yield 'sent -> delivered' => [NotificationStatus::Sent, NotificationStatus::Delivered, true];
        yield 'sent -> failed' => [NotificationStatus::Sent, NotificationStatus::Failed, true];
        yield 'queued -> delivered (forbidden)' => [NotificationStatus::Queued, NotificationStatus::Delivered, false];
        yield 'delivered -> sent (forbidden)' => [NotificationStatus::Delivered, NotificationStatus::Sent, false];
        yield 'failed -> sent (forbidden)' => [NotificationStatus::Failed, NotificationStatus::Sent, false];
        yield 'delivered -> failed (forbidden)' => [NotificationStatus::Delivered, NotificationStatus::Failed, false];
    }

    #[Test, DataProvider('transitions')]
    public function it_validates_allowed_transitions(NotificationStatus $from, NotificationStatus $to, bool $expected): void
    {
        $this->assertSame($expected, (new NotificationStatusTransition)->canApply($from, $to));
    }
}
