<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

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

    public function isAggregateColumn(): bool
    {
        return !in_array($this->value, [ColumnType::COLUMN->value, ColumnType::APPEND->value, ColumnType::METHOD->value]);
    }
}
