<?php

namespace Modules\CMS\Models\Pivot;

use Modules\Core\Helpers\HasVersions;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin IdeHelperCategorizable
 */
class Categorizable extends Pivot
{
	use HasVersions;
}
