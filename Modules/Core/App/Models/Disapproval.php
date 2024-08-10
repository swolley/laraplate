<?php

declare(strict_types=1);

namespace Modules\Core\App\Models;

use Modules\Core\App\Helpers\HasCommonObserver;
use Approval\Models\Approval as ModelsDisapproval;

/**
 * @mixin IdeHelperDisapproval
 */
class Disapproval extends ModelsDisapproval
{
    use HasCommonObserver;
}
