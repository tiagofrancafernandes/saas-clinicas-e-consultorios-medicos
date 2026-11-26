<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\BillingInterval;
use App\Enums\Central\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property SubscriptionStatus $status
 * @property BillingInterval $billing_interval
 * @property \Carbon\CarbonImmutable $current_period_start_utc
 * @property \Carbon\CarbonImmutable $current_period_end_utc
 * @property \Carbon\CarbonImmutable|null $grace_period_ends_utc
 * @property bool $is_exempt
 * @property string|null $gateway_driver
 * @property string|null $gateway_subscription_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Central\Tenant $tenant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereBillingInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCurrentPeriodEndUtc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCurrentPeriodStartUtc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereGatewayDriver($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereGatewaySubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereGracePeriodEndsUtc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereIsExempt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
