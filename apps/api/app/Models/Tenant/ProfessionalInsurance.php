<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Tenant\HealthInsurance|null $healthInsurance
 * @property-read \App\Models\Tenant\Professional|null $professional
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfessionalInsurance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfessionalInsurance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfessionalInsurance query()
 * @mixin \Eloquent
 */
final class ProfessionalInsurance extends Model
{
    use HasUuids;

    protected $table = 'professional_insurances';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'professional_id',
        'health_insurance_id',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }

    public function healthInsurance(): BelongsTo
    {
        return $this->belongsTo(HealthInsurance::class, 'health_insurance_id');
    }
}
