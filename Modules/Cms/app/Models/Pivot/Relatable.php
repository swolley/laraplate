<?php

namespace Modules\Cms\Models\Pivot;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin IdeHelperRelatable
 */
class Relatable extends Pivot
{
	protected $table = 'relatables';

	protected $casts = [
		'created_at' => 'immutable_datetime',
		'updated_at' => 'datetime',
	];
}
