<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum ActionEnum: string
{
    case SELECT = 'select';
    case INSERT = 'insert';
    case UPDATE = 'update';
    case DELETE = 'delete';
        // case RESTORE = 'restore';
    case FORCE_DELETE = 'forceDelete';
    case APPROVE = 'approve';
        // case DISAPPROVE = 'disapprove';
    case PUBLISH = 'publish';
        // case UNPUBLISH = 'unpublish';
    case IMPERSONATE = 'impersonate';
    case LOCK = 'lock';
    // case UNLOCK = 'unlock';

    /**
     * returns if is a read action
     */
    public static function isReadAction(string $action): bool
    {
        return $action === static::SELECT->value;
    }

    /**
     * returns if is a write action
     */
    public static function isWriteAction(string $action): bool
    {
        return !static::isReadAction($action);
    }
}
