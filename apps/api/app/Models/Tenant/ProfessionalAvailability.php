<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Tenant\Professional|null $professional
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfessionalAvailability newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfessionalAvailability newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfessionalAvailability query()
 * @mixin \Eloquent
 */
final class ProfessionalAvailability extends Model
{
    use HasUuids;

    protected $table = 'professional_availabilities';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'professional_id',
        'day_of_week',
        'start_time',
        'end_time',
        'slot_duration_minutes',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'day_of_week' => 'integer',
        'slot_duration_minutes' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }
}
