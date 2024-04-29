<?php

declare(strict_types=1);

namespace Modules\Core\App\Models;

use Modules\Core\App\Helpers\HasCommonObserver;
use Approval\Models\Modification as ApprovalModification;

class Modification extends ApprovalModification
{
    use HasCommonObserver;

    /**
     * @var string[]
     *
     * @psalm-suppress NonInvariantPropertyType
     */
    protected $hidden = [
        'modifiable_id',
        'modifiable_type',
        'modifier_id',
        'modifier_type',
        'md5',
        'active',
        'is_update',
        'approvers_required',
        'disapprovers_required',
    ];
}
