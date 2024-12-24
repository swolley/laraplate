<?php

declare(strict_types=1);

namespace Modules\Core\Models\Pivot;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * @mixin IdeHelperModelHasRole
 */
class ModelHasRole extends MorphPivot
{
	// protected $attributes = [
	// 	'team_id' => 1,
	// ];
}
