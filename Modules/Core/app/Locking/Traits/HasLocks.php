<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Traits;

use Modules\Core\Locking\Locked;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Exceptions\CannotUnlockException;

trait HasLocks
{
    public static function bootHasLocks(): void
    {
        static::saving(function (Model $model): void {
            if ($lock_version = request('lock_version')) {
                // @phpstan-ignore property.notFound
                $model->lock_version = $lock_version;
            }
        });
    }

    public function lock(User $user = null): self
    {
        /** @var Locked $locked */
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
        /** @var Locked $locked */
        $locked = app('locked');
        return $this->{$locked->lockedAtColumn()} !== null;
    }

    public function isLockedBy(User $user): bool
    {
        /** @var Locked $locked */
        $locked = app('locked');
        return $this->isLocked() && $this->{$locked->lockedByColumn()} === $user->id;
    }

    public function isNotLocked(): bool
    {
        return !$this->isLocked();
    }

    public function isNotLockedBy(User $user): bool
    {
        /** @var Locked $locked */
        $locked = app('locked');
        return $this->isNotLocked() && $this->{$locked->lockedByColumn()} !== $user->id;
    }

    public function unlock(): self
    {
        /** @var Locked $locked */
        $locked = app('locked');
        $lock_by_column = $locked->lockedByColumn();

        if ($locked->cannotBeUnlocked($this)) {
            throw new CannotUnlockException('This model cannot be unlocked');
        }
        $locking_user = $this->{$lock_by_column};

        if ($locking_user && $locking_user !== Auth::id()) {
            throw new CannotUnlockException('This model cannot be unlocked because locked by another user');
        }

        $this->{$locked->lockedAtColumn()} = null;
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

    public function toggleLockBy(User $user = null): self
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
        /** @var Locked $locked */
        $locked = app('locked');
        return $this->getOriginal($locked->lockedAtColumn()) === null;
    }

    public function wasUnlockedBy(User $user)
    {
        /** @var Locked $locked */
        $locked = app('locked');
        return $this->wasUnlocked() && $user->id === $this->getOriginal($locked->lockedByColumn());
    }

    public function wasLocked()
    {
        /** @var Locked $locked */
        $locked = app('locked');
        return $this->getOriginal($locked->lockedAtColumn()) !== null;
    }

    public function wasLockedBy(User $user)
    {
        /** @var Locked $locked */
        $locked = app('locked');
        return $this->wasLocked() && $user->id === $this->getOriginal($locked->lockedByColumn());
    }

    public function scopeLocked($query): void
    {
        /** @var Locked $locked */
        $locked = app('locked');
        $query->where($locked->lockedAtColumn(), '!=', null);
    }

    public function scopeLockedBy($query, User $user): void
    {
        $locked = app('locked');
        $this->scopeLocked($query);
        $query->where($locked->lockedByColumn(), $user->id);
    }

    public function scopeUnlocked($query): void
    {
        /** @var Locked $locked */
        $locked = app('locked');
        $query->where($locked->lockedAtColumn(), null);
    }

    public function scopeUnlockedBy($query, User $user): void
    {
        $locked = app('locked');
        $this->scopeUnlocked($query);
        $query->where($locked->lockedByColumn(), '!=', $user->id);
    }
}
