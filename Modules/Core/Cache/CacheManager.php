<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

class CacheManager
{
    public static function getKeyFromRequest(Request $request): string
    {
        $path = $request->getPathInfo();
        $params = $request->query();
        $user = static::getKeyFromUser($request->user());
        static::recursiveKSort($params);

        return base64_encode($path . ($user ? implode('_', $user) . '_' : '') . serialize($params));
    }

    public static function tryByRequest(Model|string|array|null $entity, Request $request, Closure $callback, int $duration = null): JsonResponse
    {
        $tags = [];
        if ($entity) {
            $models = is_array($entity) ? $entity : [$entity];

            foreach ($models as &$model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (!method_exists($model, 'usesCache') || !$model->usesCache()) {
                    return $callback();
                }
                $tags[] = static::getTableName($model);
            }
        }

        if ($user = static::getKeyFromUser($request->user())) {
            array_push($tags, ...$user);
        }
        $key = static::getKeyFromRequest($request);
        $cache = Cache::tags($tags);
        $duration = $duration !== null ? $duration : config('cache.duration');

        return $duration
            ? $cache->remember($key, $duration, $callback)
            : $cache->rememberForever($key, $callback);
    }

    public static function clearByEntity(Model|string|array $entity): void
    {
        $models = is_array($entity) ? $entity : [$entity];

        foreach ($models as &$model) {
            if (is_string($model)) {
                $model = new $model();
            }

            if (method_exists($model, 'usesCache') && $model->usesCache()) {
                Cache::tags([static::getTableName($model)])->flush();
            }
        }
    }

    public static function clearByRequest(Request $request, Model|string|array|null $entity = null): void
    {
        $key = static::getKeyFromRequest($request);
        if ($entity) {
            $entity = is_array($entity) ? $entity : [$entity];

            foreach ($entity as $model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (!method_exists($model, 'usesCache') || $model->usesCache()) {
                    Cache::tags([static::getTableName($model)])->forget($key);
                }
            }
        } else {
            Cache::forget($key);
        }
    }

    public static function clearByUser(User $user, Model|string|array|null $entity = null): void
    {
        $user_key = 'U' . $user->id;
        if ($entity) {
            $models = is_array($entity) ? $entity : [$entity];

            foreach ($models as &$model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (method_exists($model, 'usesCache') && $model->usesCache()) {
                    Cache::tags([static::getTableName($model), $user_key])->flush();
                }
            }
        } else {
            Cache::tags([$user_key])->flush();
        }
    }

    public static function clearByGroup(Role $role, Model|string|array|null $entity = null): void
    {
        $role_key = 'R' . $role->id;
        if ($entity) {
            $models = is_array($entity) ? $entity : [$entity];

            foreach ($models as &$model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (method_exists($model, 'usesCache') && $model->usesCache()) {
                    Cache::tags([static::getTableName($model), $role_key])->flush();
                }
            }
        } else {
            Cache::tags([$role_key])->flush();
        }
    }
    private static function getTableName(string|Model $entity): string
    {
        return is_string($entity) ? $entity : $entity->getTable();
    }

    private static function recursiveKSort(array|string|null &$array): void
    {
        if (is_array($array)) {
            ksort($array);

            foreach ($array as &$value) {
                static::recursiveKSort($value);
            }
        }
    }

    /**
     * @return null|(mixed|string)[]
     *
     * @psalm-return list{0?: mixed|string,...}|null
     */
    private static function getKeyFromUser(User $user): ?array
    {
        $tags = ['U' . $user->id];

        if (method_exists($user, 'groups')) {
            $group_method = 'groups';
        } elseif (method_exists($user, 'user_groups')) {
            $group_method = 'user_groups';
        } elseif (method_exists($user, 'roles')) {
            $group_method = 'roles';
        } elseif (method_exists($user, 'user_roles')) {
            $group_method = 'user_roles';
        }

        $groups = $user->{$group_method}->map(fn (Model $r): string => 'R' . (int) $r->id)->toArray();
        sort($groups);
        array_push($tags, ...$groups);

        return $tags;
    }
}
