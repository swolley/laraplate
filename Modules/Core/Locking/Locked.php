<?php

declare(strict_types=1);

namespace Modules\Core\Locking;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;

class Locked
{
    public function lockedAtColumn(): string
    {
        return config('core.locking.lock_at_column');
    }

    public function lockedByColumn(): string
    {
        return config('core.locking.lock_by_column');
    }

    public function canBeUnlocked($model): bool
    {
        $modelClass = get_class($model);
        $canBeUnlocked = $this->classesThatCanBeUnlocked();
        $unlockAllowed = $this->unlockAllowed();

        return $unlockAllowed || in_array($modelClass, $canBeUnlocked, true);
    }

    public function cannotBeUnlocked($model): bool
    {
        return !$this->canBeUnlocked($model);
    }

    public function unlockAllowed(): bool
    {
        return config('core.locking.unlock_allowed', true);
    }

    public function classesThatCanBeUnlocked(): array
    {
        return config('core.locking.can_be_unlocked', []);
    }

    public function usesHasLocks(Model $model): bool
    {
        return in_array(HasLocks::class, class_uses($model), true);
    }

    public function doesNotUseHasLocks(Model $model): bool
    {
        return !$this->usesHasLocks($model);
    }

    public function preventsModificationsOnLockedObjects(): bool
    {
        return config('core.locking.prevent_modifications_on_locked_objects', false);
    }

    public function allowsModificationsOnLockedObjects(): bool
    {
        return !$this->preventsModificationsOnLockedObjects();
    }

    public function allowsNotificationsToLockedObjects(): bool
    {
        return config('core.locking.prevent_notifications_to_locked_objects', false);
    }
}
