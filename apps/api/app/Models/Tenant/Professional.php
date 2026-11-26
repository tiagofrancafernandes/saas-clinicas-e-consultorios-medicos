<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenant\Appointment> $appointments
 * @property-read int|null $appointments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenant\ProfessionalAvailability> $availabilities
 * @property-read int|null $availabilities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenant\HealthInsurance> $healthInsurances
 * @property-read int|null $health_insurances_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenant\ProfessionalUnavailability> $unavailabilities
 * @property-read int|null $unavailabilities_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Professional newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Professional newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Professional query()
 * @mixin \Eloquent
 */
final class Professional extends Model
{
    use HasUuids;

    protected $table = 'professionals';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'name',
        'specialty',
        'license_number',
        'color_code',
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

    public function availabilities(): HasMany
    {
        return $this->hasMany(ProfessionalAvailability::class, 'professional_id');
    }

    public function unavailabilities(): HasMany
    {
        return $this->hasMany(ProfessionalUnavailability::class, 'professional_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'professional_id');
    }

    public function healthInsurances(): BelongsToMany
    {
        return $this->belongsToMany(
            HealthInsurance::class,
            'professional_insurances',
            'professional_id',
            'health_insurance_id'
        )->withPivot('is_active')->withTimestamps();
    }
}
