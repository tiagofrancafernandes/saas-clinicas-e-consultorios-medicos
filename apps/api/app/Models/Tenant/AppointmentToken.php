<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AppointmentToken extends Model
{
    use HasUuids;

    protected $table = 'appointment_tokens';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'appointment_id',
        'token_hash',
        'intended_action',
        'expires_at_utc',
        'used_at_utc',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at_utc' => 'immutable_datetime',
        'used_at_utc' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at_utc->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at_utc !== null;
    }
}
