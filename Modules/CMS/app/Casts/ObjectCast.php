<?php

declare(strict_types=1);

namespace Modules\CMS\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;

class ObjectCast implements CastsInboundAttributes
{
	public function get($model, $key, $value, $attributes)
	{
		return json_decode($value);
	}

	public function set($model, $key, $value, $attributes)
	{
		return json_encode($value);
	}
}
