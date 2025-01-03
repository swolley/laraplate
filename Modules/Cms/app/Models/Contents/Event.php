<?php

namespace Modules\Cms\Models\Contents;

use Modules\Cms\Models\Content;
use Parental\HasParent;

/**
 * @mixin IdeHelperEvent
 */
class Event extends Content
{
    use HasParent;
}