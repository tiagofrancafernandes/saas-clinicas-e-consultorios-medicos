<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\AppointmentPaymentStatus;
use App\Enums\Tenant\AppointmentStatus;
use App\Enums\Tenant\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Appointment extends Model
{
    use HasUuids;

    protected $table = 'appointments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'professional_id',
        'patient_id',
        'clinic_room_id',
        'appointment_type_id',
        'parent_appointment_id',
        'patient_package_id',
        'patient_health_insurance_id',
        'start_time_utc',
        'end_time_utc',
        'status',
        'arrived_at_utc',
        'started_at_utc',
        'completed_at_utc',
        'price_in_cents',
        'payment_status',
        'payment_method',
        'health_insurance_auth_code',
        'origin_timezone',
        'notes',
        'clinical_notes',
        'created_by_user_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'start_time_utc' => 'immutable_datetime',
        'end_time_utc' => 'immutable_datetime',
        'status' => AppointmentStatus::class,
        'arrived_at_utc' => 'immutable_datetime',
        'started_at_utc' => 'immutable_datetime',
        'completed_at_utc' => 'immutable_datetime',
        'price_in_cents' => 'integer',
        'payment_status' => AppointmentPaymentStatus::class,
        'payment_method' => PaymentMethod::class,
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function clinicRoom(): BelongsTo
    {
        return $this->belongsTo(ClinicRoom::class, 'clinic_room_id');
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    public function parentAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'parent_appointment_id');
    }

    public function patientPackage(): BelongsTo
    {
        return $this->belongsTo(PatientPackage::class, 'patient_package_id');
    }

    public function patientHealthInsurance(): BelongsTo
    {
        return $this->belongsTo(PatientHealthInsurance::class, 'patient_health_insurance_id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(AppointmentToken::class, 'appointment_id');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'appointment_id');
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'appointment_id');
    }
}
