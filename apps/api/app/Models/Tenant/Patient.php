<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Patient extends Model
{
    use HasUuids;

    protected $table = 'patients';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'phone',
        'email',
        'tax_id',
        'birth_date',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date' => 'immutable_date',
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function healthInsurances(): HasMany
    {
        return $this->hasMany(PatientHealthInsurance::class, 'patient_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(PatientPackage::class, 'patient_id');
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'patient_id');
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class, 'patient_id');
    }
}
