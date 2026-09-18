<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum BillingInterval: string
{
    case MONTHLY = 'MONTHLY';
    case BIMONTHLY = 'BIMONTHLY';
    case QUARTERLY = 'QUARTERLY';
    case YEARLY = 'YEARLY';
}
