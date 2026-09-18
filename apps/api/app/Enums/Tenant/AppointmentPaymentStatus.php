<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum AppointmentPaymentStatus: string
{
    case PENDING = 'PENDING';
    case PAID_OFFLINE = 'PAID_OFFLINE';
    case PAID_ONLINE = 'PAID_ONLINE';
    case EXEMPT = 'EXEMPT';
    case PENDING_INSURANCE_BILLING = 'PENDING_INSURANCE_BILLING';
}
