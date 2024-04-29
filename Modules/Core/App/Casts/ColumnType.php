<?php

declare(strict_types=1);

namespace Modules\Core\App\Casts;

enum ColumnType: string
{
    // common fo all the queries
    case COLUMN = 'column';
    case COUNT = 'count';
    case SUM = 'sum';
    case AVG = 'average';
    case MIN = 'min';
    case MAX = 'max';
    // only if is a mapped model and not DynanicEntity
    case APPEND = 'append';
    case METHOD = 'method';
}
