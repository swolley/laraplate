<?php

namespace Modules\Core\Casts;

use Cron\CronExpression as CoreCronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Monolog\Handler\NullHandler;

class CronExpression implements CastsAttributes
{
	/**
	 * Cast the given value.
	 *
	 * @param  array<string, mixed>  $attributes
	 */
	public function get(Model $model, string $key, mixed $value, array $attributes): ?CoreCronExpression
	{
		if (!isset($value)) return null;

		/** @var CoreCronExpression|string $value */
		return is_string($value) ? new CoreCronExpression($value) : $value;
	}

	/**
	 * Prepare the given value for storage.
	 *
	 * @param  array<string, mixed>  $attributes
	 * 
	 */
	public function set(Model $model, string $key, mixed $value, array $attributes): ?string
	{
		if (!isset($value)) return null;

		/** @var CoreCronExpression|string $value */
		return is_string($value) ? $value : $value->getExpression();
	}
}
