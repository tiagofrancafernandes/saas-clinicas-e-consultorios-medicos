<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum PaymentMethod: string
{
    case NONE = 'NONE';
    case PIX = 'PIX';
    case CREDIT_CARD = 'CREDIT_CARD';
    case DEBIT_CARD = 'DEBIT_CARD';
    case CASH = 'CASH';
    case HEALTH_INSURANCE = 'HEALTH_INSURANCE';
    case PACKAGE_CREDIT = 'PACKAGE_CREDIT';
}
