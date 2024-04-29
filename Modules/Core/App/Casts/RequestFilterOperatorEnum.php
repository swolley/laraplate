<?php

declare(strict_types=1);

namespace Modules\Core\App\Casts;

use Modules\Core\App\Helpers\HasEnumValues;

enum RequestFilterOperatorEnum: string
{
    use HasEnumValues;

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

    public static function tryFromFilterOperator(FilterOperatorEnum $operator): ?RequestFilterOperatorEnum
    {
        return match ($operator) {
            FilterOperatorEnum::GREAT => self::GREAT,
            FilterOperatorEnum::GREAT_EQUALS => self::GREAT_EQUALS,
            FilterOperatorEnum::LESS => self::LESS,
            FilterOperatorEnum::LESS_EQUALS => self::LESS_EQUALS,
            FilterOperatorEnum::LIKE => self::LIKE,
            FilterOperatorEnum::NOT_LIKE => self::NOT_LIKE,
            FilterOperatorEnum::EQUALS => self::EQUALS,
            FilterOperatorEnum::IN => self::IN,
            FilterOperatorEnum::NOT_EQUALS => self::NOT_EQUALS,
            FilterOperatorEnum::BETWEEN => self::BETWEEN,
            default => null,
        };
    }
}
