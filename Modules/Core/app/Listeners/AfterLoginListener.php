<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Support\Carbon;
use Modules\Core\Models\License;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;
use Lab404\Impersonate\Impersonate;
use Illuminate\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\UnauthorizedException;

class AfterLoginListener
{
    /**
     * Handle the event.
     */
    public function handle(Login $login): void
    {
        /** @var Authenticatable $user */
        $user = $login->user;

        if (class_uses_trait($user, Impersonate::class) && $user->isImpersonated()) {
            $impersonator = $user->getImpersonator();
            Log::info('{impersonator} is impersonating {impersonated}', ['impersonator' => $impersonator->username, 'impersonated' => $user->username]);
        } else {
            $this->checkUserLicense($user);
            $user->update(['last_login_at' => Carbon::now()]);
            if ($user->isUnlocked()) {
                Auth::logoutOtherDevices($user->password);
            }
            Log::info('{username} logged in', ['username' => $user->username]);
        }
    }

    public static function checkUserLicense(Authenticatable $user)
    {
        if (config('core.enable_user_licenses') && class_uses_trait($user, Impersonate::class)) {
            if (!$user->isGuest() && !$user->isSuperadmin() && $user->license_id === null) {
                $available_licenses = License::query()->whereDoesntHave('user')->get();
                if ($available_licenses->isEmpty()) {
                    throw new UnauthorizedException("No licenses available");
                }

                $user->license()->associate($available_licenses->first());
            }
        }
    }
}
