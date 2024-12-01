<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum SettingTypeEnum: string
{
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case STRING = 'string';
    case JSON = 'json';
    case DATE = 'date';
}
