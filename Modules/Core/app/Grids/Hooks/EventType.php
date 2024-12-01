<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Hooks;

enum EventType: string
{
    case PRE_SELECT = 'retrieving';
    case POST_SELECT = 'retrieved';
    case PRE_INSERT = 'creating';
    case POST_INSERT = 'created';
    case PRE_UPDATE = 'updating';
    case POST_UPDATE = 'updated';
    case PRE_DELETE = 'deleting';
    case POST_DELETE = 'deleted';
}
