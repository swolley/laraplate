<?php

declare(strict_types=1);

namespace Modules\Core\App\Listeners;

use Illuminate\Support\Carbon;
use Illuminate\Auth\Events\Login;
use Modules\Core\App\Models\User;
use Illuminate\Support\Facades\Log;
use Lab404\Impersonate\Impersonate;
use Illuminate\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Modules\Core\App\Models\License;
use Illuminate\Validation\UnauthorizedException;

class AfterLoginListener
{
    /**
     * Handle the event.
     */
    public function handle(Login $login): void
    {
        /** @var Authenticatable */
        $user = $login->user;

        if (class_uses_trait($user, Impersonate::class) && $user->isImpersonated()) {
            $impersonator = $user->getImpersonator();
            Log::info("{$impersonator->username} is impersonating {$user->username}");
        } else {
            $this->checkUserLicense($user);
            $user->update(['last_login_at' => Carbon::now()]);
            if ($user->isUnlocked()) {
                Auth::logoutOtherDevices($user->password);
            }
            Log::info("{$user->username} logged in");
        }
    }

    public static function checkUserLicense(User $user)
    {
        if (config('core.enable_user_licenses')) {
            if (!$user->isGuest() && !$user->isSuperadmin() && $user->license_id === null) {
                $available_licenses = License::whereDoesntHave('user')->get();
                if ($available_licenses->isEmpty()) {
                    throw new UnauthorizedException("No licenses available");
                }

                $user->license()->associate($available_licenses->first());
            }
        }
    }
}
