<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use ReflectionClass;

class CrudExecutor
{
    public const SELECT = 'get';

    public const COUNT = 'count';

    public const INSERT = 'create';

    public const UPDATE = 'save';

    public const DELETE = 'delete';

    public const FORCE_DELETE = 'force_delete';

    public const RESTORE = 'restore';

    /**
     * gets value if exists.
     */
    public static function tryFrom(string $value): ?string
    {
        $values = static::values();

        foreach ($values as $const) {
            if ($const === $value) {
                return $const;
            }
        }

        return null;
    }

    /**
     * returns if is a write action.
     */
    public static function isWriteAction(string $action): bool
    {
        return match ($action) {
            CrudExecutor::INSERT, CrudExecutor::UPDATE, CrudExecutor::DELETE, CrudExecutor::FORCE_DELETE, CrudExecutor::RESTORE => true,
            default => false,
        };
    }

    /**
     * returns if is a read action.
     */
    public static function isReadAction(string $action): bool
    {
        return !static::isWriteAction($action);
    }

    /**
     * get all available values.
     *
     * @return string[]
     */
    public static function values(): array
    {
        $refl = new ReflectionClass(static::class);
        $constants = $refl->getConstants();

        return array_values($constants);
    }
}
