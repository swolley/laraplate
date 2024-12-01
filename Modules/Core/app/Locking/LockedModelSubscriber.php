<?php

declare(strict_types=1);

namespace Modules\Core\Locking;

use Illuminate\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationSending;
use Modules\Core\Locking\Exceptions\LockedModelException;

class LockedModelSubscriber
{
    /**
     * Register the listeners for the subscriber.
     *
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            'eloquent.saving: *' => 'saving',
            'eloquent.deleting: *' => 'deleting',
            'eloquent.replicating: *' => 'replicating',
            NotificationSending::class => 'notificationSending',
        ];
    }

    public function saving(string $event, $entity): bool
    {
        if (app(Locked::class)->allowsModificationsOnLockedObjects()) {
            return true;
        }

        $model = $this->getModelFromPassedParams($entity);

        if (app('locked')->doesNotUseHasLocks($model)) {
            return true;
        }

        /** @var Locked $locked */
        $locked = app('locked');
        $lockedAtColumnName = $locked->lockedAtColumn();

        if ($model->wasUnlocked() && $model->isDirty($lockedAtColumnName)) {
            // we are locking a model
            return true;
        }

        if ($model->wasLocked() && $model->isDirty()) {
            throw new LockedModelException('This model is locked');
        }

        return true;
    }

    public function deleting(string $event, $entity): bool
    {
        if (app(Locked::class)->allowsModificationsOnLockedObjects()) {
            return true;
        }
        $model = $this->getModelFromPassedParams($entity);

        /** @var Locked $locked */
        $locked = app('locked');
        if ($locked->doesNotUseHasLocks($model)) {
            return true;
        }

        if ($model->wasUnlocked()) {
            return true;
        }

        throw new LockedModelException('This model is locked');
    }

    public function replicating(string $event, $entity): bool
    {
        /** @var Locked $locked */
        $locked = app('locked');
        if ($locked->allowsModificationsOnLockedObjects()) {
            return true;
        }
        $model = $this->getModelFromPassedParams($entity);

        if ($locked->doesNotUseHasLocks($model)) {
            return true;
        }

        if ($model->isUnlocked()) {
            return true;
        }

        throw new LockedModelException('This model is locked');
    }

    public function notificationSending(NotificationSending $event)
    {
        /** @var Locked $locked */
        $locked = app('locked');
        if ($locked->allowsNotificationsToLockedObjects()) {
            return false;
        }
        $model = $event->notifiable;

        if ($locked->doesNotUseHasLocks($model)) {
            return true;
        }

        if ($model->isUnlocked()) {
            return true;
        }

        throw new LockedModelException('This model is locked');
    }

    private function getModelFromPassedParams($params)
    {
        $model = null;

        if (is_array($params) && count($params) > 0) {
            $model = $params[0];
        }

        return $model;
    }
}
