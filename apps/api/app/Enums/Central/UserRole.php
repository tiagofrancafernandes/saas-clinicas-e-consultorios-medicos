<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum UserRole: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case CLINIC_ADMIN = 'CLINIC_ADMIN';
    case PROFESSIONAL = 'PROFESSIONAL';
    case RECEPTIONIST = 'RECEPTIONIST';
    case PATIENT = 'PATIENT';
}
