<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;

trait SoftDeletes
{
	use BaseSoftDeletes;

	protected static function bootSoftDeletes(): void
	{
		static::updating(function (Model $model): void {
			throw new UnauthorizedException('Cannot update a softdeleted model');
		});
	}
}
