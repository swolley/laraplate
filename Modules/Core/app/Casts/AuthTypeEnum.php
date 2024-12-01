<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum AuthTypeEnum: string
{
    case BASIC = 'basic';
    case BEARER = 'bearer';
}
