<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;

class ReconcilerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConsoleOutput = false;
    }

    #[Test]
    public function reconciler_republishes_stale_unpublished_notifications(): void
    {
        $batch = NotificationBatch::factory()->email()->create();

        $stale = Notification::factory()->forBatch($batch)->create([
            'recipient_address' => 'ok@example.com',
            'published_at' => null,
        ]);
        $stale->forceFill(['created_at' => now()->subMinutes(5)])->save();

        $fresh = Notification::factory()->forBatch($batch)->create([
            'recipient_address' => 'ok@example.com',
            'published_at' => null,
        ]);

        $this->assertSame(0, $this->queueSize($this->queueLow));

        $exit = Artisan::call('notifications:reconcile', ['--grace' => 60]);
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('re-published 1', Artisan::output());

        $this->assertSame(1, $this->queueSize($this->queueLow));
        $stale->refresh();
        $fresh->refresh();
        $this->assertNotNull($stale->published_at);
        $this->assertNull($fresh->published_at);
    }
}
