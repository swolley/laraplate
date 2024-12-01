<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Validation\ValidationRule;

class QueryBuilder implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            $fail("$attribute doesn't have a correct format");
        }

        if (!Arr::isList($value)) {
            if (!array_key_exists('property', $value) && !array_key_exists('filters', $value)) {
                $fail("$attribute doesn't have a correct format");
            }
            if (array_key_exists('property', $value)) {
                if (!array_key_exists('operator', $value)) {
                    $fail("$attribute \"operator\" is required");
                }
                if (!array_key_exists('value', $value)) {
                    $fail("$attribute \"value\" is required");
                }
            }
            if (array_key_exists('filters', $value)) {
                if (!Arr::isList($value['filters'])) {
                    $fail("$attribute filters doesn't have a correct format");
                }
                $this->validate($attribute . ".filters", $value['filters'], $fail);
            }
        } else {
            foreach ($value as $idx => $filter) {
                $this->validate($attribute . ".$idx", $filter, $fail);
            }
        }
    }
}
