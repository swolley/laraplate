<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @phpstan-type HasACLType HasACL
 */
trait HasACL
{
    protected static function bootHasACL(): void
    {
        static::addGlobalScope('acl', function (Builder $builder): void {});
    }

    public function acl(): HasMany
    {
        return $this->hasMany();
    }
}
