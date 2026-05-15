<?php

declare(strict_types=1);

namespace App\Application\Notifications\DataTransferObjects;

use Spatie\LaravelData\Data;

class RecipientData extends Data
{
    public function __construct(
        public string $id,
        public string $address,
    ) {}
}
