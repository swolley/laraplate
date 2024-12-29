<?php

namespace Modules\Cms\Models\Pivot;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin IdeHelperAuthorable
 */
class Authorable extends Pivot
{
    protected $table = 'authorables';

    protected $casts = [
        'created_at' => 'immutable_datetime',
        'updated_at' => 'datetime',
    ];
}
