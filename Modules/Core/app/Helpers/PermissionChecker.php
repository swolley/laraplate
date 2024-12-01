<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Contracts\Container\BindingResolutionException;

class PermissionChecker
{
    /**
     * check user permission on requested resource.
     *
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public static function checkPermissions(Request $request, string $entity, ?string $operation = null, ?string $connection = null, ?Collection $permissions = null): bool
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard();
        $guard_name = $guard->name;

        if (!$connection) {
            $connection = 'default';
        }

        if (!$permissions) {
            $user = $request->user();

            if (!$permissions && $user && $user->isSuperAdmin()) {
                return true;
            }

            if (!$user) {
                // TODO: temporaneo da eliminare dopo lo sviluppo dei componenti e i test
                $user = user_class()::whereName('root')->first();

                // $user = user_class()::whereName('anonymous')->first();
                if ($user) {
                    Auth::login($user);
                    $request->setUserResolver(fn() => $user);
                }
            }

            if (!$permissions) {
                return $user ? $user->can($connection . '.' . $entity . '.*') : false;
            }
            $permissions = $user?->getAllPermissions() ?: collect([]);
        }

        return $permissions->filter(
            fn($permission) => $permission->guard === $guard_name && $operation && $operation !== '*'
                ? $permission->name === $connection . '.' . $entity . '.' . $operation
                : Str::startsWith($permission->name, $connection . '.' . $entity . '.'),
        )->isNotEmpty();
    }

    /**
     * ensure user permission on requested resource or throw exception.
     *
     *
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     * @throws UnauthorizedException
     */
    public static function ensurePermissions(Request $request, string $entity, ?string $operation = null, ?string $connection = null, ?Collection $permissions = null): true
    {
        if (!static::checkPermissions($request, $entity, $operation, $connection, $permissions)) {
            throw new UnauthorizedException('User not allowed to access this resource');
        }

        return true;
    }
}
