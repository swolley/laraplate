<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

enum FieldType: string
{
    case COLUMN = 'column';
    case COUNT = 'witCount';
    case SUM = 'withSum';
    case AVG = 'withAverage';
    case MIN = 'withMin';
    case MAX = 'withMax';
    case METHOD = 'method';
    case APPEND = 'append';
}
