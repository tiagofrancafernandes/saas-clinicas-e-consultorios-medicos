<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenant\HealthInsurancePlan> $plans
 * @property-read int|null $plans_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenant\Professional> $professionals
 * @property-read int|null $professionals_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HealthInsurance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HealthInsurance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HealthInsurance query()
 * @mixin \Eloquent
 */
final class HealthInsurance extends Model
{
    use HasUuids;

    protected $table = 'health_insurances';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'ans_code',
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

    public function plans(): HasMany
    {
        return $this->hasMany(HealthInsurancePlan::class, 'health_insurance_id');
    }

    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(
            Professional::class,
            'professional_insurances',
            'health_insurance_id',
            'professional_id'
        )->withPivot('is_active')->withTimestamps();
    }
}
