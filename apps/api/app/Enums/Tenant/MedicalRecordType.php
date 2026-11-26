<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum MedicalRecordType: string
{
    case CLINICAL_NOTE = 'CLINICAL_NOTE';
    case SOAP = 'SOAP';
    case PSYCHOTHERAPY = 'PSYCHOTHERAPY';
    case ANAMNESIS = 'ANAMNESIS';
    case ADDENDUM = 'ADDENDUM';
}
