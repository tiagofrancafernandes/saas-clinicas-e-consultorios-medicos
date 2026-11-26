<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Tenant\Appointment|null $appointment
 * @property-read \App\Models\Tenant\PatientPackage|null $patientPackage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientCreditLedger newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientCreditLedger newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientCreditLedger query()
 * @mixin \Eloquent
 */
final class PatientCreditLedger extends Model
{
    use HasUuids;

    protected $table = 'patient_credit_ledger';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'patient_package_id',
        'appointment_id',
        'credits_delta',
        'operation_type',
        'description',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'credits_delta' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function patientPackage(): BelongsTo
    {
        return $this->belongsTo(PatientPackage::class, 'patient_package_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}
