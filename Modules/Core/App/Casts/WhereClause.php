<?php

declare(strict_types=1);

namespace Modules\Core\App\Casts;

enum WhereClause: string
{
    case AND = 'and';
    case OR = 'or';
}
