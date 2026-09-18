<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PatientPackage extends Model
{
    use HasUuids;

    protected $table = 'patient_packages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'patient_id',
        'package_id',
        'total_credits',
        'remaining_credits',
        'expires_at_utc',
        'payment_status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'total_credits' => 'integer',
        'remaining_credits' => 'integer',
        'expires_at_utc' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function creditLedgers(): HasMany
    {
        return $this->hasMany(PatientCreditLedger::class, 'patient_package_id');
    }
}
