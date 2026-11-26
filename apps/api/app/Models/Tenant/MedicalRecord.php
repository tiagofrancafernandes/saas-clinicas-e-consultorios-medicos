<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\MedicalRecordStatus;
use App\Enums\Tenant\MedicalRecordType;
use App\Enums\Tenant\RecordVisibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property MedicalRecordStatus $status
 * @property MedicalRecordType $type
 * @property RecordVisibility $visibility
 * @property array<string, mixed>|null $structured_data
 * @property-read Appointment|null $appointment
 * @property-read Patient|null $patient
 * @property-read Professional|null $professional
 * @property-read MedicalRecord|null $parentRecord
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MedicalRecord> $addendums
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalRecord query()
 * @mixin \Eloquent
 */
final class MedicalRecord extends Model
{
    use HasUuids;

    protected $table = 'medical_records';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'patient_id',
        'professional_id',
        'appointment_id',
        'parent_record_id',
        'created_by_user_id',
        'type',
        'visibility',
        'status',
        'title',
        'content',
        'structured_data',
        'finalized_at_utc',
        'finalized_by_user_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => MedicalRecordType::class,
        'visibility' => RecordVisibility::class,
        'status' => MedicalRecordStatus::class,
        'structured_data' => 'array',
        'finalized_at_utc' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function parentRecord(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_record_id');
    }

    public function addendums(): HasMany
    {
        return $this->hasMany(self::class, 'parent_record_id')->orderBy('created_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
