<?php

namespace Modules\Cms\Models\Pivot;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin IdeHelperCategorizable
 */
class Categorizable extends Pivot
{
	protected $table = 'categorizables';

	protected $casts = [
		'created_at' => 'immutable_datetime',
		'updated_at' => 'datetime',
	];
}
