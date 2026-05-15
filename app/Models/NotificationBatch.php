<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\Priority;
use Database\Factories\NotificationBatchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property string $id
 * @property Channel $channel
 * @property Priority $priority
 * @property string $message
 * @property string|null $idempotency_key
 */
class NotificationBatch extends Model
{
    /** @use HasFactory<NotificationBatchFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'id',
        'channel',
        'priority',
        'message',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'priority' => Priority::class,
        ];
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }
}
