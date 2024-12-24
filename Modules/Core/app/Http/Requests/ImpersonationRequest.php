<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Lab404\Impersonate\Impersonate;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\FormRequest;

class ImpersonationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var null|User $user */
        $user = auth()->user();

        return $user && class_uses_trait($user, Impersonate::class) && $user->canImpersonate();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'user' => 'required|number|exists:User,id',
        ];
    }
}
