<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\WhatsAppCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property WhatsAppCategory $category
 * @property-read string $masked_token
 * @property-read string|int|null $id
 * @property-read ?string $instance_name
 * @property-read ?string $attendant_name
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppInstance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppInstance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppInstance query()
 * @mixin \Eloquent
 */
final class WhatsAppInstance extends Model
{
    use HasUuids;

    protected $table = 'whatsapp_instances';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'label',
        'phone_number',
        'instance_name',
        'token',
        'category',
        'is_default',
        'active',
        'attendant_name',
        'settings',
        'last_connected_at_utc',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'masked_token',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'token' => 'encrypted',
        'category' => WhatsAppCategory::class,
        'is_default' => 'boolean',
        'active' => 'boolean',
        'settings' => 'array',
        'last_connected_at_utc' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function getMaskedTokenAttribute(): string
    {
        $raw = (string) ($this->token ?? '');

        if (strlen($raw) <= 8) {
            return str_repeat('*', strlen($raw));
        }

        return substr($raw, 0, 4) . '...' . substr($raw, -4);
    }
}
