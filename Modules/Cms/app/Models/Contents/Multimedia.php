<?php

namespace Modules\Cms\Models\Contents;

use Modules\Cms\Models\Content;
use Parental\HasParent;

/**
 * @mixin IdeHelperMultimedia
 */
class Multimedia extends Content
{
    use HasParent;
}