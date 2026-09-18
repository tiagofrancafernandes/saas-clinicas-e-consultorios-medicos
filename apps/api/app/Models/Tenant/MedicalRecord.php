<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\RecordVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'type',
        'visibility',
        'title',
        'content',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'visibility' => RecordVisibility::class,
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
}
