<?php

declare(strict_types=1);

namespace Modules\Core\App\Models\Pivot;

use Modules\Core\App\Helpers\HasCommonObserver;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Modules\Core\App\Models\Pivot\RoleHasPermissions.
 *
 * @method static \Illuminate\Database\Eloquent\Builder|RoleHasPermissions newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RoleHasPermissions newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RoleHasPermissions query()
 *
 * @mixin \Eloquent
 */
class RoleHasPermissions extends Pivot
{
    use HasCommonObserver;
}
