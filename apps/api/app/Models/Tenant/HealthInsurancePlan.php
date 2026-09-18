<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HealthInsurancePlan extends Model
{
    use HasUuids;

    protected $table = 'health_insurance_plans';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'health_insurance_id',
        'name',
        'plan_code',
        'requires_prior_authorization',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'requires_prior_authorization' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function healthInsurance(): BelongsTo
    {
        return $this->belongsTo(HealthInsurance::class, 'health_insurance_id');
    }
}
