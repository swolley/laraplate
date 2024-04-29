<?php

declare(strict_types=1);

namespace Modules\Core\App\Casts;

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

    public static function tryFromRequestOperator(RequestFilterOperatorEnum|string $operator): ?self
    {
        return match ($operator) {
            RequestFilterOperatorEnum::GREAT, RequestFilterOperatorEnum::GREAT->value => self::GREAT,
            RequestFilterOperatorEnum::GREAT_EQUALS, RequestFilterOperatorEnum::GREAT_EQUALS->value => self::GREAT_EQUALS,
            RequestFilterOperatorEnum::LESS, RequestFilterOperatorEnum::LESS->value => self::LESS,
            RequestFilterOperatorEnum::LESS_EQUALS, RequestFilterOperatorEnum::LESS_EQUALS->value => self::LESS_EQUALS,
            RequestFilterOperatorEnum::LIKE, RequestFilterOperatorEnum::LIKE->value => self::LIKE,
            RequestFilterOperatorEnum::NOT_LIKE, RequestFilterOperatorEnum::NOT_LIKE->value => self::NOT_LIKE,
            RequestFilterOperatorEnum::EQUALS, RequestFilterOperatorEnum::EQUALS->value => self::EQUALS,
            RequestFilterOperatorEnum::IN, RequestFilterOperatorEnum::IN->value => self::IN,
            RequestFilterOperatorEnum::NOT_EQUALS, RequestFilterOperatorEnum::NOT_EQUALS->value => self::NOT_EQUALS,
            RequestFilterOperatorEnum::BETWEEN, RequestFilterOperatorEnum::BETWEEN->value => self::BETWEEN,
            default => null,
        };
    }
}
