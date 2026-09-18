<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class File extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'files';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'fileable_type',
        'fileable_id',
        'collection',
        'visibility',
        'disk',
        'file_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'uploaded_by_user_id',
        'purged_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'size_bytes' => 'integer',
        'purged_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
        'deleted_at' => 'immutable_datetime',
    ];

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }
}
