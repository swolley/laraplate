<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use Illuminate\Foundation\Auth\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function creating(User $user): void
    {
        if (!$user->username) {
            $user->username = $user->email;
        }

        if (!$user->lang) {
            $user->lang = App::getLocale();
        }

        if (!$user->password) {
            $user->password = Hash::make(Str::password());
        }
    }

    public function created(User $user): void
    {
        if (!$user->hasVerifiedEmail() && config('core.verify_new_user')) {
            $user->sendEmailVerificationNotification();
        }
    }

    public function deleted(User $user): void
    {
        if (config('core.enable_user_licenses') && $user->license_id) {
            $user->license_id = null;
            $user->save();
        }
    }
}
