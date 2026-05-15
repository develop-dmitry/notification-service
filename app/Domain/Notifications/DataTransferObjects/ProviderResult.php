<?php

declare(strict_types=1);

namespace App\Domain\Notifications\DataTransferObjects;

use App\Domain\Notifications\Enums\ProviderResultStatus;
use Spatie\LaravelData\Data;

class ProviderResult extends Data
{
    public function __construct(
        public ProviderResultStatus $status,
        public ?string $providerMessageId = null,
        public ?string $error = null,
    ) {}

    public static function accepted(string $providerMessageId): self
    {
        return new self(ProviderResultStatus::Accepted, $providerMessageId);
    }

    public static function transientFailure(string $error): self
    {
        return new self(ProviderResultStatus::TransientFailure, null, $error);
    }

    public static function permanentFailure(string $error): self
    {
        return new self(ProviderResultStatus::PermanentFailure, null, $error);
    }
}
