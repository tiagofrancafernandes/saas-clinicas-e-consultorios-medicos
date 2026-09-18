<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ClinicRoom extends Model
{
    use HasUuids;

    protected $table = 'clinic_rooms';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'description',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'clinic_room_id');
    }
}
