<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum SubscriptionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case PAST_DUE = 'PAST_DUE';
    case SUSPENDED = 'SUSPENDED';
    case CANCELLED = 'CANCELLED';
    case COURTESY = 'COURTESY';

    public function allowsWrites(): bool
    {
        return in_array($this, [self::ACTIVE, self::PAST_DUE, self::COURTESY], true);
    }
}
