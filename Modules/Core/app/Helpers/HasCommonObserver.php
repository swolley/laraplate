<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use function class_uses_trait;

use Illuminate\Database\Eloquent\Model;
use Overtrue\LaravelVersionable\Versionable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Locking\HasOptimisticLocking;
use Illuminate\Validation\UnauthorizedException;

/**
 * @phpstan-type HasCommonObserverType HasCommonObserver
 */
trait HasCommonObserver
{
    use Versionable, HasOptimisticLocking;

    // protected static bool $useCache = false;

    // protected $guarded = ['id'];

    protected array $dontVersionable = ['created_at', 'updated_at', 'deleted_at', 'last_login_at'];

    protected static function bootHasCommonObserver(): void
    {
        static::updating(function (Model $model): void {
            if (class_uses_trait(static::class, SoftDeletes::class)) {
                throw new UnauthorizedException('Cannot update a softdeleted model');
            }
        });
    }
}
