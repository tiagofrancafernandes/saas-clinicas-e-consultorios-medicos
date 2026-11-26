<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum MedicalRecordStatus: string
{
    case DRAFT = 'DRAFT';
    case FINALIZED = 'FINALIZED';

    public function isEditable(): bool
    {
        return $this === static::DRAFT;
    }
}
