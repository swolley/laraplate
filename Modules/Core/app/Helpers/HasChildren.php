<?php

namespace Modules\Core\app\Helpers;

use Parental\HasChildren as ParentalHasChildren;

trait HasChildren
{
	use ParentalHasChildren;

	public function getChildTypes(): array
	{
		if (property_exists(static::class, 'childTypes')) {
			return static::$childTypes;
		}

		if (method_exists($this, 'childTypes')) {
			return $this->childTypes();
		}


		return [];
	}
}
