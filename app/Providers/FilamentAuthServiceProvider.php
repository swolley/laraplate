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
        Fortify::loginView(fn() => view('filament.auth.login'));

        // Configura il percorso di registrazione di Fortify
        Fortify::registerView(fn() => view('filament.auth.register'));

        // Configura il percorso di reset password di Fortify
        Fortify::requestPasswordResetLinkView(fn() => view('filament.auth.forgot-password'));

        Fortify::resetPasswordView(fn($request) => view('filament.auth.reset-password', ['request' => $request]));

        // Configura il percorso di verifica email di Fortify
        Fortify::verifyEmailView(fn() => view('filament.auth.verify-email'));

        // Configura il percorso di conferma password di Fortify
        Fortify::confirmPasswordView(fn() => view('filament.auth.confirm-password'));

        // Configura il percorso di 2FA di Fortify
        Fortify::twoFactorChallengeView(fn() => view('filament.auth.two-factor-challenge'));
    }
}
