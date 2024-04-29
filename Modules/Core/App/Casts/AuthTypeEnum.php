<?php

declare(strict_types=1);

namespace Modules\Core\App\Casts;

enum AuthTypeEnum: string
{
    case BASIC = 'basic';
    case BEARER = 'bearer';
}
