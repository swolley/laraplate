<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum SortDirection: string
{
        // common fo all the queries
    case ASC = 'asc';
    case DESC = 'desc';
}
