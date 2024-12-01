<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum RequestFilterOperator: string
{
    case GREAT = 'gt';
    case GREAT_EQUALS = 'ge';
    case LESS = 'lt';
    case LESS_EQUALS = 'le';
    case LIKE = 'like';
    case NOT_LIKE = 'not like';
    case EQUALS = 'eq';
    case IN = 'in';
    case NOT_EQUALS = 'ne';
    case BETWEEN = 'between';

    public static function tryFromFilterOperator(FilterOperator $operator): ?static
    {
        return match ($operator) {
            FilterOperator::GREAT => self::GREAT,
            FilterOperator::GREAT_EQUALS => self::GREAT_EQUALS,
            FilterOperator::LESS => self::LESS,
            FilterOperator::LESS_EQUALS => self::LESS_EQUALS,
            FilterOperator::LIKE => self::LIKE,
            FilterOperator::NOT_LIKE => self::NOT_LIKE,
            FilterOperator::EQUALS => self::EQUALS,
            FilterOperator::IN => self::IN,
            FilterOperator::NOT_EQUALS => self::NOT_EQUALS,
            FilterOperator::BETWEEN => self::BETWEEN,
        };
    }
}
