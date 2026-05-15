<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NotificationBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @property NotificationBatch $resource
 */
class BatchAcceptedResource extends JsonResource
{
    public function __construct(NotificationBatch $batch, public readonly int $accepted)
    {
        parent::__construct($batch);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'batch_id' => $this->resource->id,
            'accepted' => $this->accepted,
            'channel' => $this->resource->channel->value,
            'priority' => $this->resource->priority->value,
        ];
    }
}
