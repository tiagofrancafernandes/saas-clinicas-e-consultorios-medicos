<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProfessionalDelegation extends Model
{
    use HasUuids;

    protected $table = 'professional_delegations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'grantor_professional_id',
        'grantee_professional_id',
        'patient_id',
        'starts_at_utc',
        'ends_at_utc',
        'reason',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'starts_at_utc' => 'immutable_datetime',
        'ends_at_utc' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'grantor_professional_id');
    }

    public function grantee(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'grantee_professional_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
