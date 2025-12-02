<?php

declare(strict_types=1);

// ide-helper-custom.php

namespace Illuminate\Support\Facades {
    /**
     * @method static \Illuminate\Contracts\Cache\Repository memo() Get the memoized cache repository
     * @method static mixed tryByRequest(\Illuminate\Database\Eloquent\Model|string|array<string|object>|null $entity, \Illuminate\Http\Request $request, \Closure $callback, ?int $duration = null) Try to extract from cache or by specified callback using request info
     * @method static void clearByEntity(\Illuminate\Database\Eloquent\Model|string|array<string|object> $entity) Clear cache by the specified entity
     * @method static void clearByRequest(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model|string|array<string|object>|null $entity = null) Clear cache by request extracted info
     * @method static void clearByUser(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model|string|array<string|object>|null $entity = null) Clear cache elements by user and only by entity if specified
     * @method static void clearByGroup(\Spatie\Permission\Models\Role $role, \Illuminate\Database\Eloquent\Model|string|array<string|object>|null $entity = null) Clear cache elements by user group and only by entity if specified
     * @method static array getCacheTags(array|string $tags = []) Get the cache tags
     */
    final class Cache {}
}
