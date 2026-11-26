<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\NotificationLogStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property NotificationLogStatus $status
 * @property-read Appointment|null $appointment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog query()
 * @mixin \Eloquent
 */
final class NotificationLog extends Model
{
    use HasUuids;

    protected $table = 'notification_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'appointment_id',
        'patient_id',
        'channel',
        'type',
        'recipient',
        'external_message_id',
        'status',
        'payload',
        'response_metadata',
        'scheduled_at_utc',
        'sent_at_utc',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => NotificationLogStatus::class,
        'payload' => 'array',
        'response_metadata' => 'array',
        'scheduled_at_utc' => 'immutable_datetime',
        'sent_at_utc' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
