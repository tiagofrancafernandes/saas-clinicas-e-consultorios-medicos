<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'channel',
        'type',
        'recipient',
        'external_message_id',
        'status',
        'payload',
        'response_metadata',
        'sent_at_utc',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'response_metadata' => 'array',
        'sent_at_utc' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}
