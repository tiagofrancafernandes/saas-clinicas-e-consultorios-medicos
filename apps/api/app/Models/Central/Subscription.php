<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\BillingInterval;
use App\Enums\Central\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Subscription extends Model
{
    use HasUuids;

    protected $table = 'subscriptions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'status',
        'billing_interval',
        'current_period_start_utc',
        'current_period_end_utc',
        'grace_period_ends_utc',
        'is_exempt',
        'gateway_driver',
        'gateway_subscription_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => SubscriptionStatus::class,
        'billing_interval' => BillingInterval::class,
        'is_exempt' => 'boolean',
        'current_period_start_utc' => 'immutable_datetime',
        'current_period_end_utc' => 'immutable_datetime',
        'grace_period_ends_utc' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
