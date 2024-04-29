<?php

declare(strict_types=1);

namespace Modules\Core\App\Models\Pivot;

use Modules\Core\App\Helpers\HasCommonObserver;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * Modules\Core\App\Models\Pivot\ModelHasPermissions.
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ModelHasPermissions newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ModelHasPermissions newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ModelHasPermissions query()
 *
 * @mixin \Eloquent
 */
class ModelHasPermissions extends MorphPivot
{
    use HasCommonObserver;
}
