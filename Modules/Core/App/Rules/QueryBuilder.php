<?php

namespace Modules\Core\App\Rules;

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
        if (isset($value) && (!array_key_exists('operator', $value) || !array_key_exists('filters', $value) || !Arr::isList($value['filters']))) {
            $fail("$attribute doesn't have a correct format");
        }
    }
}
