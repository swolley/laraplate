<?php

namespace Modules\Core\Actions\Fortify;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Helpers\HasValidations;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     */
    public function update(Model&Authenticatable $user, array $input): void
    {
        if (class_uses_trait($user, HasValidations::class)) {
            $rules = $user->getOperationRules('update');
        } else {
            $rules = [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'string',
                    'email',
                    'max:255',
                    // @phpstan-ignore property.notFound
                    Rule::unique(user_class())->ignore($user->id),
                ],
            ];
        }

        $rules = array_merge($rules, [
            'password' => ['sometimes|password|confirmed'],
            'current_password' => ['current_password:web|required_with:password|exclude_without:password'],
        ]);

        $validated = Validator::make($input, $rules)->validate();
        Arr::forget($validated, ['current_password', 'password_confirmation']);
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        if (
            // @phpstan-ignore property.notFound
            isset($validated['email']) && $validated['email'] !== $user->email &&
            $user instanceof MustVerifyEmail
        ) {
            $this->updateVerifiedUser($user, $validated);
        } else {
            $user->forceFill($validated)->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(Model&MustVerifyEmail $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
