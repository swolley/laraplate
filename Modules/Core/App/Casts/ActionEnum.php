<?php

declare(strict_types=1);

namespace Modules\Core\App\Casts;

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
    case IMPERSONATE = 'impersonate';
    case LOCK = 'lock';
    // case UNLOCK = 'unlock';
}
