<?php

declare(strict_types=1);

namespace App\Providers;

use Laravel\Fortify\Fortify;
use Illuminate\Support\ServiceProvider;

final class FilamentAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Configura il percorso di login di Fortify
        Fortify::loginView(function () {
            return view('filament.auth.login');
        });

        // Configura il percorso di registrazione di Fortify
        Fortify::registerView(function () {
            return view('filament.auth.register');
        });

        // Configura il percorso di reset password di Fortify
        Fortify::requestPasswordResetLinkView(function () {
            return view('filament.auth.forgot-password');
        });

        Fortify::resetPasswordView(function ($request) {
            return view('filament.auth.reset-password', ['request' => $request]);
        });

        // Configura il percorso di verifica email di Fortify
        Fortify::verifyEmailView(function () {
            return view('filament.auth.verify-email');
        });

        // Configura il percorso di conferma password di Fortify
        Fortify::confirmPasswordView(function () {
            return view('filament.auth.confirm-password');
        });

        // Configura il percorso di 2FA di Fortify
        Fortify::twoFactorChallengeView(function () {
            return view('filament.auth.two-factor-challenge');
        });
    }
}
