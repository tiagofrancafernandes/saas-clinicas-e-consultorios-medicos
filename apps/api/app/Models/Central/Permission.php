<?php

declare(strict_types=1);

namespace App\Models\Central;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

final class Permission extends SpatiePermission
{
    use CentralConnection;
}
