<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Notifications\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Console\Command;

class ReconcileNotifications extends Command
{
    protected $signature = 'notifications:reconcile {--grace=60 : Grace period in seconds before re-publishing a queued notification}';

    protected $description = 'Re-publish notifications that were committed to the DB but never reached the broker.';

    public function handle(): int
    {
        $graceSeconds = (int) $this->option('grace');
        $threshold = now()->subSeconds($graceSeconds);

        $republished = 0;

        $stale = Notification::query()
            ->where('status', NotificationStatus::Queued->value)
            ->whereNull('published_at')
            ->where('created_at', '<', $threshold)->oldest()
            ->limit(1000)
            ->get();

        foreach ($stale as $notification) {
            dispatch(new SendNotificationJob($notification->id))
                ->onQueue($notification->priority->queue());
            $notification->published_at = now()->toImmutable();
            $notification->save();
            $republished++;
        }

        $this->info("Reconciler re-published {$republished} notification(s).");

        return self::SUCCESS;
    }
}
