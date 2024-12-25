<?php

namespace Modules\Cms\Models\Pivot;

use Modules\Core\Helpers\HasVersions;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin IdeHelperAuthorable
 */
class Authorable extends Pivot
{
    use HasVersions;
}
