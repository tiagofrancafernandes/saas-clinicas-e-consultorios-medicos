<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum NotificationLogStatus: string
{
    case SCHEDULED = 'SCHEDULED';
    case PROCESSING = 'PROCESSING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';

    public function isCancellable(): bool
    {
        return $this === static::SCHEDULED;
    }
}
