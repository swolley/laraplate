<?php

declare(strict_types=1);

// ide-helper-custom.php

namespace Illuminate\Support\Facades {
    /**
     * @method static mixed tryByRequest(\Illuminate\Database\Eloquent\Model|string|array|null $entity, \Illuminate\Http\Request $request, \Closure $callback, ?int $duration = null)
     * @method static void clearByEntity(\Illuminate\Database\Eloquent\Model|string|array $entity)
     * @method static void clearByRequest(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model|string|array|null $entity = null)
     * @method static void clearByUser(\Illuminate\Foundation\Auth\User $user, \Illuminate\Database\Eloquent\Model|string|array|null $entity = null)
     * @method static void clearByGroup(\Spatie\Permission\Models\Role $group, \Illuminate\Database\Eloquent\Model|string|array|null $entity = null)
     */
    final class Cache {}
}
