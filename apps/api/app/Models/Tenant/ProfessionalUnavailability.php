<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProfessionalUnavailability extends Model
{
    use HasUuids;

    protected $table = 'professional_unavailabilities';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'professional_id',
        'start_time_utc',
        'end_time_utc',
        'reason',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'start_time_utc' => 'immutable_datetime',
        'end_time_utc' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }
}
