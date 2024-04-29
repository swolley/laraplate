<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Traits;

use Modules\Core\App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Exceptions\CannotUnlockException;

trait HasLocks
{
    public static function bootHasLocks(): void
    {
        static::saving(function (Model $model): void {
            if ($lock_version = request('lock_version')) {
                $model->lock_version = $lock_version;
            }
        });
    }

    public function lock(?User $user = null): self
    {
        $locked = app('locked');
        $this->{$locked->lockedAtColumn()} = now();

        if ($user) {
            $this->{$locked->lockedByColumn()} = $user->id;
        }
        $this->save();

        return $this;
    }

    public function lockBy(User $user): self
    {
        return $this->lock($user);
    }

    public function isLocked(): bool
    {
        return $this->{app('locked')->lockedAtColumn()} !== null;
    }

    public function isLockedBy(User $user): bool
    {
        return $this->isLocked() && $this->{app('locked')->lockedByColumn()} === $user->id;
    }

    public function isNotLocked(): bool
    {
        return !$this->isLocked();
    }

    public function isNotLockedBy(User $user): bool
    {
        return $this->isNotLocked() && $this->{app('locked')->lockedByColumn()} !== $user->id;
    }

    public function unlock(): self
    {
        $locked = app('locked');
        $lock_by_column = $locked->lockedByColumn();

        if ($locked->cannotBeUnlocked($this)) {
            throw new CannotUnlockException('This model cannot be unlocked');
        }
        $locking_user = $this->{$lock_by_column};

        if ($locking_user && $locking_user !== Auth::id()) {
            throw new CannotUnlockException('This model cannot be unlocked because locked by another user');
        }

        $this->{$this->lockedAtColumn()} = null;
        $this->{$lock_by_column} = null;
        $this->save();

        return $this;
    }

    public function isUnlocked(): bool
    {
        return  !$this->isLocked();
    }

    public function isUnlockedBy(User $user): bool
    {
        return  !$this->isLockedBy($user);
    }

    public function isNotUnlocked(): bool
    {
        return !$this->isUnlocked();
    }

    public function isNotUnlockedBy(User $user): bool
    {
        return !$this->isUnlockedBy($user);
    }

    public function toggleLock(?User $user = null): self
    {
        if ($this->isLocked()) {
            $this->unlock();
        } else {
            $this->lock($user);
        }

        return $this;
    }

    public function toggleLockBy(User $user): self
    {
        if (!$user) {
            $user = Auth::user();
        }

        if ($this->isLocked()) {
            $this->unlock();
        } else {
            $this->lock($user);
        }

        return $this;
    }

    public function wasUnlocked()
    {
        return $this->getOriginal(app('locked')->lockedAtColumn()) === null;
    }

    public function wasUnlockedBy(User $user)
    {
        return $this->wasUnlocked() && $user->id === $this->getOriginal(app('locked')->lockedByColumn());
    }

    public function wasLocked()
    {
        return $this->getOriginal(app('locked')->lockedAtColumn()) !== null;
    }

    public function wasLockedBy(User $user)
    {
        return $this->wasLocked() && $user->id === $this->getOriginal(app('locked')->lockedByColumn());
    }

    public function scopeLocked($query): void
    {
        $query->where(app('locked')->lockedAtColumn(), '!=', null);
    }

    public function scopeLockedBy($query, User $user): void
    {
        $locked = app('locked');
        $this->scopeLocked($query);
        $query->where($locked->lockedByColumn(), $user->id);
    }

    public function scopeUnlocked($query): void
    {
        $query->where(app('locked')->lockedAtColumn(), null);
    }

    public function scopeUnlockedBy($query, User $user): void
    {
        $locked = app('locked');
        $this->scopeUnlocked($query);
        $query->where($locked->lockedByColumn(), '!=', $user->id);
    }
}
