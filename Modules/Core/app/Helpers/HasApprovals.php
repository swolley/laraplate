<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Override;
use Approval\Traits\RequiresApproval;

/**
 * @phpstan-type HasApprovalsType HasApprovals
 */
trait HasApprovals
{
    use RequiresApproval;

    public function initializeHasApprovals(): void
    {
        if (preview()) {
            $this->append('preview');
            $this->makeHidden('preview');
        }
        $this->deleteWhenDisapproved = true;
    }

    /**
     */
    public function getPreviewAttribute(): ?array
    {
        if (!session('preview', false)) {
            return null;
        }

        $preview = $this->attributesToArray();

        foreach ($this->modifications()->oldest()->get(['modifications']) as $modification) {
            foreach ($modification->modifications as $key => $mod) {
                $preview[$key] = $mod['modified'];
            }
        }

        return $preview;
    }

    #[Override]
    public function toArray()
    {
        if (!$this->preview) {
            return parent::toArray();
        }

        $preview = array_merge($this->preview, $this->relationsToArray());
        $preview['_'] = array_merge($this->attributesToArray());

        foreach ($preview['_'] as $key => $value) {
            if ($preview[$key] === $value) {
                unset($preview['_'][$key]);
            }
        }

        return $preview;
    }

    protected function requiresApprovalWhen($modifications): bool
    {
        return !empty($modifications);
    }
}
