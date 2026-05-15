<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Override;
use Tests\TestCase;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    protected string $queueHigh = 'test.notifications.high';

    protected string $queueLow = 'test.notifications.low';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.queues.high' => $this->queueHigh,
            'notifications.queues.low' => $this->queueLow,
        ]);

        $this->purgeQueues();
        $this->flushIdempotencyKeys();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->purgeQueues();
        parent::tearDown();
    }

    protected function purgeQueues(): void
    {
        /** @var RabbitMQQueue $queue */
        $queue = Queue::connection('rabbitmq');
        foreach ([$this->queueHigh, $this->queueLow] as $name) {
            try {
                $queue->declareQueue($name, true);
                $queue->purge($name);
            } catch (Throwable) {
                // queue may not exist yet
            }
        }
    }

    protected function flushIdempotencyKeys(): void
    {
        Cache::store('redis')->flush();
    }

    protected function queueSize(string $queue): int
    {
        /** @var RabbitMQQueue $rabbit */
        $rabbit = Queue::connection('rabbitmq');

        return $rabbit->size($queue);
    }

    protected function runWorkerOnce(string $queue): void
    {
        Artisan::call('queue:work', [
            'connection' => 'rabbitmq',
            '--queue' => $queue,
            '--once' => true,
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);
    }
}
