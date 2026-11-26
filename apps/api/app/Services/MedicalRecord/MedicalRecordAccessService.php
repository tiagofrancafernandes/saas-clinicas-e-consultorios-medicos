<?php

declare(strict_types=1);

namespace App\Services\MedicalRecord;

use App\Enums\Tenant\MedicalRecordStatus;
use App\Enums\Tenant\RecordVisibility;
use App\Models\Tenant\MedicalRecord;
use App\Models\Tenant\Patient;
use App\Models\Tenant\Professional;
use App\Models\Tenant\ProfessionalDelegation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final class MedicalRecordAccessService
{
    public static function canAccess(
        MedicalRecord $record,
        Professional $requestingProfessional,
        ?CarbonImmutable $nowUtc = null
    ): bool {
        $now = $nowUtc ?? CarbonImmutable::now();

        // 0. DRAFT records are strictly private to their author
        if ($record->status === MedicalRecordStatus::DRAFT) {
            return $record->professional_id === $requestingProfessional->id;
        }

        // 1. PUBLIC_CLINIC and SHARED_TEAM are accessible by any active clinic professional
        if ($record->visibility === RecordVisibility::PUBLIC_CLINIC || $record->visibility === RecordVisibility::SHARED_TEAM) {
            return $requestingProfessional->is_active;
        }

        // 2. PRIVATE: accessible if requesting professional is the author
        if ($record->professional_id === $requestingProfessional->id) {
            return true;
        }

        // 3. PRIVATE: accessible if active delegation exists from author (grantor) to requesting (grantee)
        return ProfessionalDelegation::query()
            ->where('grantor_professional_id', $record->professional_id)
            ->where('grantee_professional_id', $requestingProfessional->id)
            ->where(function (Builder $query) use ($record): void {
                $query->where('patient_id', $record->patient_id)
                    ->orWhereNull('patient_id');
            })
            ->where('starts_at_utc', '<=', $now)
            ->where('ends_at_utc', '>=', $now)
            ->exists();
    }

    /**
     * @throws AuthorizationException
     */
    public static function authorizeAccess(
        MedicalRecord $record,
        Professional $requestingProfessional,
        ?CarbonImmutable $nowUtc = null
    ): void {
        if (! static::canAccess($record, $requestingProfessional, $nowUtc)) {
            throw new AuthorizationException('Access denied: this medical record is confidential and no active delegation was found.');
        }
    }

    /**
     * @return Builder<MedicalRecord>
     */
    public static function getAccessibleRecordsQuery(
        Patient $patient,
        Professional $requestingProfessional,
        ?CarbonImmutable $nowUtc = null
    ): Builder {
        $now = $nowUtc ?? CarbonImmutable::now();

        // Find author IDs that have actively delegated access to the requesting professional
        $grantorIdsWithActiveDelegation = ProfessionalDelegation::query()
            ->where('grantee_professional_id', $requestingProfessional->id)
            ->where(function (Builder $query) use ($patient): void {
                $query->where('patient_id', $patient->id)
                    ->orWhereNull('patient_id');
            })
            ->where('starts_at_utc', '<=', $now)
            ->where('ends_at_utc', '>=', $now)
            ->pluck('grantor_professional_id')
            ->toArray();

        return MedicalRecord::query()
            ->where('patient_id', $patient->id)
            ->where(function (Builder $query) use ($requestingProfessional, $grantorIdsWithActiveDelegation): void {
                $query->where('professional_id', $requestingProfessional->id)
                    ->orWhere(function (Builder $finalized) use ($grantorIdsWithActiveDelegation): void {
                        $finalized->where('status', MedicalRecordStatus::FINALIZED->value)
                            ->where(function (Builder $vis) use ($grantorIdsWithActiveDelegation): void {
                                $vis->whereIn('visibility', [RecordVisibility::PUBLIC_CLINIC->value, RecordVisibility::SHARED_TEAM->value])
                                    ->orWhere(function (Builder $sub) use ($grantorIdsWithActiveDelegation): void {
                                        $sub->where('visibility', RecordVisibility::PRIVATE->value)
                                            ->whereIn('professional_id', $grantorIdsWithActiveDelegation);
                                    });
                            });
                    });
            });
    }
}
