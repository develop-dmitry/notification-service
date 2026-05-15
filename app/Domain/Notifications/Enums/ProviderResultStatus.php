<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

enum ProviderResultStatus: string
{
    case Accepted = 'accepted';
    case TransientFailure = 'transient_failure';
    case PermanentFailure = 'permanent_failure';
}
