<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Package extends Model
{
    use HasUuids;

    protected $table = 'packages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'appointment_type_id',
        'name',
        'session_quantity',
        'total_price_in_cents',
        'validity_days',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'session_quantity' => 'integer',
        'total_price_in_cents' => 'integer',
        'validity_days' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    public function patientPackages(): HasMany
    {
        return $this->hasMany(PatientPackage::class, 'package_id');
    }
}
