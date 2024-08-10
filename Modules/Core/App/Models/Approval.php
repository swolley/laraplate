<?php

declare(strict_types=1);

namespace Modules\Core\App\Models;

use Approval\Models\Approval as ModelsApproval;
use Modules\Core\App\Helpers\HasCommonObserver;

/**
 * @mixin IdeHelperApproval
 */
class Approval extends ModelsApproval
{
    use HasCommonObserver;
}
