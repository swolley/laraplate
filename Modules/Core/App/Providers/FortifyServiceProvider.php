<?php

namespace Modules\Core\App\Providers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Modules\Core\App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Hash;
use Modules\Core\App\Models\License;
use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Modules\Core\App\Http\Requests\LoginRequest;
use App\Actions\Fortify\UpdateUserProfileInformation;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->instance(LogoutResponse::class, new class implements LogoutResponse
        {
            public function toResponse($request)
            {
                return $request->wantsJson()
                    ? redirect()->route('core.auth.userInfo')
                    : redirect()->intended(Fortify::redirects('logout', '/'));
            }
        });

        $this->app->instance(LoginResponse::class, new class implements LoginResponse
        {
            public function toResponse($request)
            {
                return $request->wantsJson()
                    ? redirect()->route('core.auth.userInfo')
                    : redirect()->intended(Fortify::redirects('login'));
            }
        });

        $this->app->instance(RegisterResponse::class, new class implements RegisterResponse
        {
            public function toResponse($request)
            {
                return $request->wantsJson()
                    ? redirect()->route('core.auth.userInfo')
                    : redirect()->intended(Fortify::redirects('register'));
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        Fortify::authenticateUsing(function (LoginRequest $request) {
            $username = $request->request->get('username');
            $email = $request->request->get('email');
            $password = $request->request->get('password');

            $query = User::has('roles');
            if ($username) {
                $query->where('username', $username);
            } else {
                $query->where('email', $email);
            }
            /** @var User|null */
            $user = $query->first();

            if (!$user || !Hash::check($password, $user->password)) return null;

            // verify registration confirmation
            if (class_uses_trait($user, MustVerifyEmail::class) && !$user->hasVerifiedEmail()) {
                Log::warning("Cannot login user {$user->name} because registration was not confirmed");
                return null;
            }

            // verify user license
            if (config('core.enable_user_licenses')) {
                $available_license = License::doesntHave('user')->first();
                if (!$user->license_id && !$available_license && $user->roles->filter(fn ($role) => $role->name === 'superadmin')->isEmpty()) {
                    Log::warning("No free licenses available for user login");
                    return null;
                }
                if (!$user->license_id) {
                    $user->license()->associate(License::free()->first());
                }
            }

            $request->setUserResolver(fn () => $user);
            return $user;
        });
    }
}
