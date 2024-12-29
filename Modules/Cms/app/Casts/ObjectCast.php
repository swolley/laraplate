<?php

declare(strict_types=1);

namespace Modules\Cms\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;

class ObjectCast implements CastsInboundAttributes
{
	public function get($model, $key, $value, $attributes): object
	{
		return json_decode($value) ?? new \stdClass();
	}

	public function set($model, $key, $value, $attributes): string
	{
		return json_encode($value);
	}
}
