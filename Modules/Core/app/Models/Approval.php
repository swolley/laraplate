<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Approval\Models\Approval as ModelsApproval;
use Modules\Core\Helpers\HasCommonObserver;

/**
 * @mixin IdeHelperApproval
 */
class Approval extends ModelsApproval
{
    use HasCommonObserver;
}
