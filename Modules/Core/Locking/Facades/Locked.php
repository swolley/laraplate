<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Sfolador\Locked\Locked
 */
class Locked extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Modules\Core\Locking\Locked::class;
    }
}
