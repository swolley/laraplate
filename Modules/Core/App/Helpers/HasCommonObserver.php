<?php

declare(strict_types=1);

namespace Modules\Core\App\Helpers;

use function class_uses_trait;

use Illuminate\Database\Eloquent\Model;
use Mpociot\Versionable\VersionableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Locking\HasOptimisticLocking;
use Illuminate\Validation\UnauthorizedException;

trait HasCommonObserver
{
    use VersionableTrait, HasOptimisticLocking;

    // protected static bool $useCache = false;

    // protected $guarded = ['id'];

    protected array $dontVersionFields = ['created_at', 'updated_at', 'deleted_at', 'last_login_at'];

    protected static function bootHasCommonObserver(): void
    {
        static::updating(function (Model $model): void {
            if (class_uses_trait(static::class, SoftDeletes::class)) {
                throw new UnauthorizedException('Cannot update a softdeleted model');
            }
        });
    }
}
