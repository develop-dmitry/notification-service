<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Notifications\Enums\ProviderResultStatus;
use Carbon\CarbonImmutable;
use Database\Factories\NotificationAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property string $notification_id
 * @property int $attempt_no
 * @property ProviderResultStatus $result
 * @property string|null $error
 * @property CarbonImmutable $created_at
 */
class NotificationAttempt extends Model
{
    /** @use HasFactory<NotificationAttemptFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'notification_id',
        'attempt_no',
        'result',
        'error',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'result' => ProviderResultStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Notification, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
