<?php

namespace Modules\Cms\Models\Pivot;

use Modules\Core\Helpers\HasVersions;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @mixin IdeHelperFieldable
 */
class Fieldable extends Pivot implements Sortable
{
	use HasVersions, SortableTrait;

	public $incrementing = true;

	protected $table = 'fieldables';

	protected $attributes = [
		'is_required' => false,
		'order_column' => 0,
	];

	protected $casts = [
		'is_required' => 'boolean',
		'order_column' => 'integer',
		'default' => 'json',
		'created_at' => 'immutable_datetime',
		'updated_at' => 'datetime',
	];

	protected $sortable = [
		'order_column_name' => 'order_column',
		'sort_when_creating' => true,
	];
}
