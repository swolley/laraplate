<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Approval\Models\Modification as ApprovalModification;

/**
 * @mixin IdeHelperModification
 */
class Modification extends ApprovalModification
{
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
