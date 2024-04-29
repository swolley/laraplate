<?php

declare(strict_types=1);

namespace Modules\Core\App\Models\Pivot;

use Modules\Core\App\Helpers\HasCommonObserver;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * Modules\Core\App\Models\Pivot\ModelHasRoles.
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ModelHasRoles newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ModelHasRoles newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ModelHasRoles query()
 *
 * @mixin \Eloquent
 */
class ModelHasRoles extends MorphPivot
{
    use HasCommonObserver;
}
