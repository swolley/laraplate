<?php

namespace Modules\Core\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

/**
 * @phpstan-type PasswordValidationRulesType PasswordValidationRules
 */
trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }
}
