<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Enums\ProviderResultStatus;
use App\Domain\Notifications\Exceptions\TransientDeliveryException;
use App\Domain\Notifications\Services\NotificationProviderResolver;
use App\Models\Notification;
use App\Models\NotificationAttempt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $notificationId,
    ) {}

    public function handle(NotificationProviderResolver $resolver): void
    {
        $retryError = null;

        DB::transaction(function () use ($resolver, &$retryError): void {
            /** @var Notification|null $notification */
            $notification = Notification::query()
                ->whereKey($this->notificationId)
                ->lockForUpdate()
                ->first();

            if ($notification === null) {
                return;
            }

            if ($notification->status !== NotificationStatus::Queued) {
                return;
            }

            $attemptNo = $notification->attempts_count + 1;
            $provider = $resolver->for($notification->channel);

            try {
                $result = $provider->send($notification);
            } catch (Throwable $e) {
                $this->recordAttempt($notification->id, $attemptNo, ProviderResultStatus::TransientFailure, $e->getMessage());
                $notification->attempts_count = $attemptNo;
                $notification->last_error = $e->getMessage();
                $notification->save();
                $retryError = $e->getMessage();

                return;
            }

            $this->recordAttempt($notification->id, $attemptNo, $result->status, $result->error);
            $notification->attempts_count = $attemptNo;

            switch ($result->status) {
                case ProviderResultStatus::Accepted:
                    $notification->status = NotificationStatus::Sent;
                    $notification->provider_message_id = $result->providerMessageId;
                    $notification->sent_at = now()->toImmutable();
                    $notification->last_error = null;
                    $notification->save();
                    break;

                case ProviderResultStatus::PermanentFailure:
                    $notification->status = NotificationStatus::Failed;
                    $notification->failed_at = now()->toImmutable();
                    $notification->last_error = $result->error;
                    $notification->save();
                    break;

                case ProviderResultStatus::TransientFailure:
                    $notification->last_error = $result->error;
                    $notification->save();
                    $retryError = $result->error;
                    break;
            }
        });

        if ($retryError !== null) {
            throw new TransientDeliveryException($retryError);
        }
    }

    public function failed(Throwable $exception): void
    {
        /** @var Notification|null $notification */
        $notification = Notification::query()->find($this->notificationId);
        if ($notification === null) {
            return;
        }

        if (in_array($notification->status, [NotificationStatus::Delivered, NotificationStatus::Failed], true)) {
            return;
        }

        $notification->status = NotificationStatus::Failed;
        $notification->failed_at = now()->toImmutable();
        $notification->last_error = $exception->getMessage();
        $notification->save();

        Log::warning('SendNotificationJob exhausted retries', [
            'notification_id' => $this->notificationId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function recordAttempt(
        string $notificationId,
        int $attemptNo,
        ProviderResultStatus $result,
        ?string $error,
    ): void {
        NotificationAttempt::query()->create([
            'notification_id' => $notificationId,
            'attempt_no' => $attemptNo,
            'result' => $result->value,
            'error' => $error,
            'created_at' => now(),
        ]);
    }
}
