<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum WhatsAppCategory: string
{
    case DEFAULT = 'DEFAULT';
    case REMINDERS = 'REMINDERS';
    case MARKETING = 'MARKETING';
    case SUPPORT = 'SUPPORT';
    case BILLING = 'BILLING';
}
