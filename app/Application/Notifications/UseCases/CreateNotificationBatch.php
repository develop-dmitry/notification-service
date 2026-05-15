<?php

declare(strict_types=1);

namespace App\Application\Notifications\UseCases;

use App\Application\Notifications\DataTransferObjects\CreateBatchData;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateNotificationBatch
{
    /**
     * @return array{batch: NotificationBatch, notificationIds: list<string>}
     */
    public function execute(CreateBatchData $data, ?string $idempotencyKey = null): array
    {
        /** @var array{batch: NotificationBatch, notificationIds: list<string>} $result */
        $result = DB::transaction(function () use ($data, $idempotencyKey): array {
            $batch = NotificationBatch::query()->create([
                'channel' => $data->channel,
                'priority' => $data->priority,
                'message' => $data->message,
                'idempotency_key' => $idempotencyKey,
            ]);

            $now = now();
            $rows = [];
            $ids = [];
            foreach ($data->recipients as $recipient) {
                $id = (string) Str::uuid();
                $ids[] = $id;
                $rows[] = [
                    'id' => $id,
                    'batch_id' => $batch->id,
                    'recipient_id' => $recipient->id,
                    'recipient_address' => $recipient->address,
                    'channel' => $data->channel->value,
                    'priority' => $data->priority->value,
                    'status' => NotificationStatus::Queued->value,
                    'attempts_count' => 0,
                    'last_error' => null,
                    'provider_message_id' => null,
                    'published_at' => null,
                    'sent_at' => null,
                    'delivered_at' => null,
                    'failed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            Notification::query()->insert($rows);

            return ['batch' => $batch, 'notificationIds' => $ids];
        });

        $queue = $data->priority->queue();
        foreach ($result['notificationIds'] as $id) {
            dispatch(new SendNotificationJob($id))->onQueue($queue);
        }

        Notification::query()
            ->whereIn('id', $result['notificationIds'])
            ->update(['published_at' => now()]);

        return $result;
    }
}
