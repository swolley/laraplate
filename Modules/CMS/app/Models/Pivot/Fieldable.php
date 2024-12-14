<?php

namespace Modules\CMS\Models\Pivot;

use Modules\Core\Helpers\HasVersions;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin IdeHelperFieldable
 */
class Fieldable extends Pivot
{
	use HasVersions;
}
