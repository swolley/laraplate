<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Facades\Validator;

trait HasCommandUtils
{
	private function validationCallback(string $attribute, string $value, array $validations)
	{
		if (!array_key_exists($attribute, $validations)) {
			return null;
		}
		$validator = Validator::make([$attribute => $value], array_filter($validations, fn($k) => $k === $attribute, ARRAY_FILTER_USE_KEY))->stopOnFirstFailure(true);

		if (!$validator->passes()) {
			return $validator->messages()->first();
		}

		return null;
	}
}
