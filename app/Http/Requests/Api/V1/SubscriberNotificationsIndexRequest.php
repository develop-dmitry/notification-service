<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\NotificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriberNotificationsIndexRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 20;

    private const int MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(NotificationStatus::class)],
            'channel' => ['nullable', Rule::enum(Channel::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function statusFilter(): ?NotificationStatus
    {
        $value = $this->validated('status');

        return is_string($value) ? NotificationStatus::from($value) : null;
    }

    public function channelFilter(): ?Channel
    {
        $value = $this->validated('channel');

        return is_string($value) ? Channel::from($value) : null;
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', self::DEFAULT_PER_PAGE);
    }

    public function page(): int
    {
        return (int) $this->validated('page', 1);
    }
}
