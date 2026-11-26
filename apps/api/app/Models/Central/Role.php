<?php

declare(strict_types=1);

namespace App\Models\Central;

use Spatie\Permission\Models\Role as SpatieRole;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

final class Role extends SpatieRole
{
    use CentralConnection;
}
