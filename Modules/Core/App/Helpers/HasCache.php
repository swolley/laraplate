<?php

declare(strict_types=1);

namespace Modules\Core\App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-type HasCacheType HasCache
 */
trait HasCache
{
    protected static function bootHasCache(): void
    {
        static::saved(function (Model $model): void {
            if (Cache::supportsTags()) {
                Cache::tags([$model->getTable])->flush();
            }
        });
    }

    public function usesCache(): bool
    {
        return true;
    }
}
