<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @property Notification $resource
 *
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        /** @var Notification $n */
        $n = $this->resource;

        return [
            'id' => $n->id,
            'batch_id' => $n->batch_id,
            'recipient_id' => $n->recipient_id,
            'recipient_address' => $n->recipient_address,
            'channel' => $n->channel->value,
            'priority' => $n->priority->value,
            'status' => $n->status->value,
            'attempts_count' => $n->attempts_count,
            'last_error' => $n->last_error,
            'provider_message_id' => $n->provider_message_id,
            'sent_at' => $n->sent_at?->toIso8601String(),
            'delivered_at' => $n->delivered_at?->toIso8601String(),
            'failed_at' => $n->failed_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ];
    }
}
