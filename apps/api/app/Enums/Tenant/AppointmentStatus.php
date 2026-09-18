<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum AppointmentStatus: string
{
    case SCHEDULED = 'SCHEDULED';
    case CONFIRMED = 'CONFIRMED';
    case ARRIVED = 'ARRIVED';
    case IN_CONSULTATION = 'IN_CONSULTATION';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case NO_SHOW = 'NO_SHOW';

    public function isFinalized(): bool
    {
        return in_array($this, [static::COMPLETED, static::CANCELLED, static::NO_SHOW], true);
    }

    public function isBillable(): bool
    {
        return $this === static::COMPLETED;
    }
}
