<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum FilterOperator: string
{

    case GREAT = '>';
    case GREAT_EQUALS = '>=';
    case LESS = '<';
    case LESS_EQUALS = '<=';
    case LIKE = 'like';
    case NOT_LIKE = 'not like';
    case EQUALS = '=';
    case IN = 'in';
    case NOT_EQUALS = '!=';
    case BETWEEN = 'between';

    public static function tryFromRequestOperator(RequestFilterOperator|string $operator): ?self
    {
        return match ($operator) {
            RequestFilterOperator::GREAT, RequestFilterOperator::GREAT->value => self::GREAT,
            RequestFilterOperator::GREAT_EQUALS, RequestFilterOperator::GREAT_EQUALS->value => self::GREAT_EQUALS,
            RequestFilterOperator::LESS, RequestFilterOperator::LESS->value => self::LESS,
            RequestFilterOperator::LESS_EQUALS, RequestFilterOperator::LESS_EQUALS->value => self::LESS_EQUALS,
            RequestFilterOperator::LIKE, RequestFilterOperator::LIKE->value => self::LIKE,
            RequestFilterOperator::NOT_LIKE, RequestFilterOperator::NOT_LIKE->value => self::NOT_LIKE,
            RequestFilterOperator::EQUALS, RequestFilterOperator::EQUALS->value => self::EQUALS,
            RequestFilterOperator::IN, RequestFilterOperator::IN->value => self::IN,
            RequestFilterOperator::NOT_EQUALS, RequestFilterOperator::NOT_EQUALS->value => self::NOT_EQUALS,
            RequestFilterOperator::BETWEEN, RequestFilterOperator::BETWEEN->value => self::BETWEEN,
            default => null,
        };
    }
}
