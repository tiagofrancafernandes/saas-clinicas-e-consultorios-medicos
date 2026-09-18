<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum RecordVisibility: string
{
    case PRIVATE = 'PRIVATE';
    case SHARED_TEAM = 'SHARED_TEAM';
    case PUBLIC_CLINIC = 'PUBLIC_CLINIC';
}
