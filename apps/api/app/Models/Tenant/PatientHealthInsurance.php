<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PatientHealthInsurance extends Model
{
    use HasUuids;

    protected $table = 'patient_health_insurances';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'patient_id',
        'health_insurance_id',
        'health_insurance_plan_id',
        'card_number',
        'expiration_date',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expiration_date' => 'immutable_date',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function healthInsurance(): BelongsTo
    {
        return $this->belongsTo(HealthInsurance::class, 'health_insurance_id');
    }

    public function healthInsurancePlan(): BelongsTo
    {
        return $this->belongsTo(HealthInsurancePlan::class, 'health_insurance_plan_id');
    }
}
