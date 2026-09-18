<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WaitlistEntry extends Model
{
    use HasUuids;

    protected $table = 'waitlist_entries';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'patient_id',
        'professional_id',
        'appointment_type_id',
        'preferred_date',
        'preferred_shift',
        'status',
        'notified_at_utc',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'preferred_date' => 'immutable_date',
        'notified_at_utc' => 'immutable_datetime',
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

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }
}
