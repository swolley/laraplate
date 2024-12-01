<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class QueryColumn implements ValidationRule
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
        if (
            !is_string($value) && !is_array($value) ||
            (is_array($value) && (!array_key_exists('name', $value) || !array_key_exists('type', $value)))
        ) {
            $fail("$attribute doesn't have a correct format");
        }
    }
}
