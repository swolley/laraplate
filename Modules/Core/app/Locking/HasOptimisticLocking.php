<?php

declare(strict_types=1);

namespace Modules\Core\Locking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Locking\Exceptions\StaleModelLockingException;

trait HasOptimisticLocking
{
    /**
     * Indicates that models uses locking or not?
     *
     * @var bool
     */
    protected $lock = true;

    /**
     * Hooks model events to add lock version if not set.
     *
     */
    protected static function bootOptimisticLocking(): void
    {
        static::creating(function (Model $model) {
            // @phpstan-ignore method.notFound
            if ($model->currentLockVersion() == null) {
                $model->{static::lockVersionColumn()} = 1;
            }

            return $model;
        });
    }

    /**
     * Name of the lock version column.
     *
     */
    protected static function lockVersionColumn(): string
    {
        return config('core.locking.lock_version_column');
    }

    /**
     * Current lock version value.
     *
     */
    public function currentLockVersion(): int
    {
        return $this->getAttribute(static::lockVersionColumn());
    }

    /**
     * Enables optimistic locking for this model instance.
     *
     * @return $this
     */
    public function enableLocking()
    {
        $this->lock = true;

        return $this;
    }

    /**
     * Perform a model update operation respecting optimistic locking.
     * If the lock fails it will throw a "StaleModelLockingException".
     *
     */
    protected function performUpdate(Builder $query): bool
    {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // First we need to create a fresh query instance and touch the creation and
        // update timestamp on the model which are maintained by us for developer
        // convenience. Then we will just continue saving the model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            $versionColumn = static::lockVersionColumn();

            $this->setKeysForSaveQuery($query);

            // If model locking is enabled, the lock version check constraint is
            // added to the update query, as every update on the model increments the version
            // by exactly "1" we will increment the value by one for update, then.
            if ($this->lockingEnabled()) {
                $query->where($versionColumn, '=', $this->currentLockVersion());
            }

            $beforeUpdateVersion = $this->currentLockVersion();

            $this->setAttribute($versionColumn, $newVersion = $beforeUpdateVersion + 1);
            $dirty[$versionColumn] = $newVersion;

            // If there is no record affected by our update query,
            // It means that the record has been updated by another process,
            // Or has been deleted, as we treat "delete" as an act of update
            // we throw the exception in this situation anyway.
            $affected = $query->update($dirty);

            if ($affected === 0) {
                $this->setAttribute($versionColumn, $beforeUpdateVersion);

                throw new StaleModelLockingException("Model has been changed during update.");
            }

            $this->fireModelEvent('updated', false);

            $this->syncChanges();
        }

        return true;
    }

    /**
     * Indicates that optimistic locking is enabled for this model
     * instance or not.
     *
     */
    protected function lockingEnabled(): bool
    {
        return $this->lock === null ? true : $this->lock;
    }

    /**
     * Disables optimistic locking for this model instance.
     *
     * @return $this
     */
    protected function disableLocking()
    {
        $this->lock = false;

        return $this;
    }
}
