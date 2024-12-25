<?php

namespace Modules\Cms\Models\Pivot;

use Modules\Core\Helpers\HasVersions;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin IdeHelperRelatable
 */
class Relatable extends Pivot
{
	use HasVersions;
}
