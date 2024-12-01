<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum WhereClause: string
{
    case AND = 'and';
    case OR = 'or';
}
