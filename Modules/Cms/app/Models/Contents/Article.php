<?php

namespace Modules\Cms\Models\Contents;

use Modules\Cms\Models\Content;
use Parental\HasParent;

/**
 * @mixin IdeHelperArticle
 */
class Article extends Content
{
    use HasParent;
}