<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AppointmentType extends Model
{
    use HasUuids;

    protected $table = 'appointment_types';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'duration_minutes',
        'price_in_cents',
        'is_return',
        'max_return_days_limit',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'duration_minutes' => 'integer',
        'price_in_cents' => 'integer',
        'is_return' => 'boolean',
        'max_return_days_limit' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'appointment_type_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'appointment_type_id');
    }
}
