<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Auth\Events\Logout;

class TrackUserLogout
{
    public function handle(Logout $event): void
    {
        /** @var Model $user */
        $user = $event->user;
        // $sessionId = session()->getId();
        // Remove session from the database
        $user->license?->delete();
    }
}
