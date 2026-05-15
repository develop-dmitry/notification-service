<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Enums\Priority;
use Carbon\CarbonImmutable;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property string $id
 * @property string $batch_id
 * @property string $recipient_id
 * @property string $recipient_address
 * @property Channel $channel
 * @property Priority $priority
 * @property NotificationStatus $status
 * @property int $attempts_count
 * @property string|null $last_error
 * @property string|null $provider_message_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'id',
        'batch_id',
        'recipient_id',
        'recipient_address',
        'channel',
        'priority',
        'status',
        'attempts_count',
        'last_error',
        'provider_message_id',
        'published_at',
        'sent_at',
        'delivered_at',
        'failed_at',
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
            'status' => NotificationStatus::class,
            'attempts_count' => 'integer',
            'published_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<NotificationBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }

    /**
     * @return HasMany<NotificationAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(NotificationAttempt::class);
    }
}
