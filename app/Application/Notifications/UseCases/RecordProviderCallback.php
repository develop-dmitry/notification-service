<?php

declare(strict_types=1);

namespace App\Application\Notifications\UseCases;

use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Services\NotificationStatusTransition;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class RecordProviderCallback
{
    public function __construct(private readonly NotificationStatusTransition $transition) {}

    public function execute(string $notificationId, NotificationStatus $newStatus, ?string $error = null): ?Notification
    {
        return DB::transaction(function () use ($notificationId, $newStatus, $error): ?Notification {
            /** @var Notification|null $notification */
            $notification = Notification::query()
                ->whereKey($notificationId)
                ->lockForUpdate()
                ->first();

            if ($notification === null) {
                return null;
            }

            if (! $this->transition->canApply($notification->status, $newStatus)) {
                return $notification;
            }

            $notification->status = $newStatus;
            if ($newStatus === NotificationStatus::Delivered) {
                $notification->delivered_at = now()->toImmutable();
            }
            if ($newStatus === NotificationStatus::Failed) {
                $notification->failed_at = now()->toImmutable();
                $notification->last_error = $error;
            }
            $notification->save();

            return $notification;
        });
    }
}
