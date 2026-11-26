<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Appointment> $appointments
 * @property-read int|null $appointments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PatientHealthInsurance> $healthInsurances
 * @property-read int|null $health_insurances_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MedicalRecord> $medicalRecords
 * @property-read int|null $medical_records_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PatientPackage> $packages
 * @property-read int|null $packages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WaitlistEntry> $waitlistEntries
 * @property-read int|null $waitlist_entries_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient query()
 * @mixin \Eloquent
 */
final class Patient extends Model
{
    use HasApiTokens;
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
        'internal_note',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'internal_note',
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
