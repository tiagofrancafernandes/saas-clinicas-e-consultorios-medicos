<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $event
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<array-key, mixed>|null $payload
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Central\Tenant|null $tenant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentralAuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentralAuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentralAuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentralAuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentralAuditLog whereEvent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentralAuditLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentralAuditLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentralAuditLog wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentralAuditLog whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentralAuditLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CentralAuditLog whereUserAgent($value)
 * @mixin \Eloquent
 */
final class CentralAuditLog extends Model
{
    use HasUuids;

    protected $table = 'central_audit_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'event',
        'ip_address',
        'user_agent',
        'payload',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
