<?php

declare(strict_types=1);

namespace Modules\Core\App\Http\Controllers;

use Throwable;
use TypeError;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use UnexpectedValueException;
use Modules\Core\App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Core\App\Helpers\ResponseBuilder;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\App\Listeners\AfterLoginListener;
use Modules\Core\App\Http\Resources\UserInfoResponse;
use Modules\Core\App\Http\Requests\UserConfigsRequest;
use Modules\Core\App\Http\Requests\ImpersonationRequest;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Illuminate\Contracts\Container\BindingResolutionException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

class UserController extends Controller
{
    /**
     * @return ((mixed|string[])[]|false|int|mixed|string)[]
     *
     * @psalm-return array{id: 'anonymous'|int, name: string, username: string, email: string, groups: array<int, mixed>, canImpersonate: false|mixed, permissions: array<list<string>>}
     */
    public static function parseUserInfo(?User $user = null): UserInfoResponse
    {
        return new UserInfoResponse($user);
    }

    public static function parseAnonymousUserInfo(): array
    {
        return static::parseUserInfo();
    }

    /**
     * get current session user info.
     *
     *
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws TypeError
     * @throws PermissionDoesNotExist
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function userInfo(Request $request): HttpFoundationResponse
    {
        /** @var null|User $user */
        $user = Auth::user();
        // questo riassegna una licenza all'utente in sessione se da comando si è fatto un aggiornamento delle licenze che ha disassociato i riferimenti
        try {
            if ($user) AfterLoginListener::checkUserLicense($user);
            return (new ResponseBuilder($request))
                ->setData(static::parseUserInfo($user))
                ->json();
        } catch (UnauthorizedException $ex) {
            return (new ResponseBuilder($request))
                ->setError($ex->getMessage())
                ->setStatus(Response::HTTP_UNAUTHORIZED)
                ->json();
        }
    }

    /**
     * Impersonate a user.
     *
     *
     * @throws ValidationException
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws BindingResolutionException
     * @throws TypeError
     * @throws PermissionDoesNotExist
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function impersonate(ImpersonationRequest $request): HttpFoundationResponse
    {
        $user_to_impersonate_id = $request->validated()['user'];
        $user_to_impersonate = user_class()::findOrFail($user_to_impersonate_id);
        $current_user = Auth::user();
        $current_user->impersonate($user_to_impersonate);

        return (new ResponseBuilder($request))
            ->setData(static::parseUserInfo())
            ->json();
    }

    /**
     * Leave user impersonation.
     *
     *
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws BindingResolutionException
     * @throws TypeError
     * @throws PermissionDoesNotExist
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function leaveImpersonate(Request $request): HttpFoundationResponse
    {
        /** @var User */
        $current_user = Auth::user();
        $current_user->leaveImpersonation();

        return (new ResponseBuilder($request))
            ->setData(static::parseUserInfo())
            ->json();
    }

    // /**
    //  * Update user preferences.
    //  *
    //  *
    //  * @throws ValidationException
    //  * @throws BindingResolutionException
    //  * @throws Throwable
    //  * @throws UnexpectedValueException
    //  */
    // public function updateConfigs(UserConfigsRequest $request): HttpFoundationResponse
    // {
    //     $validated = $request->validated();
    //     $request->user()->update($validated);

    //     return (new ResponseBuilder($request))
    //         ->setData($validated)
    //         ->json();
    // }
}
