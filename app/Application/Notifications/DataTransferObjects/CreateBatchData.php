<?php

declare(strict_types=1);

namespace App\Application\Notifications\DataTransferObjects;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\Priority;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class CreateBatchData extends Data
{
    /**
     * @param  DataCollection<int, RecipientData>  $recipients
     */
    public function __construct(
        public Channel $channel,
        public Priority $priority,
        public string $message,
        public DataCollection $recipients,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = is_array($context->payload) ? $context->payload : [];
        $channelValue = $payload['channel'] ?? null;
        $addressRule = match ($channelValue) {
            'email' => ['required', 'string', 'email:rfc'],
            'sms' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            default => ['required', 'string'],
        };

        return [
            'channel' => ['required', Rule::enum(Channel::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'message' => ['required', 'string', 'max:4096'],
            'recipients' => ['required', 'array', 'min:1', 'max:10000'],
            'recipients.*.id' => ['required', 'string', 'max:64'],
            'recipients.*.address' => $addressRule,
        ];
    }
}
