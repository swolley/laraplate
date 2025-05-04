<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Http\Request;
use Modules\Core\Auth\Services\AuthenticationService;

final class Login extends Component
{
    public $email;

    public $password;

    public $remember = false;

    protected $rules = [
        'email' => 'required|email',
        'password' => 'required',
    ];

    public function authenticate()
    {
        $this->validate();

        $request = new Request([
            'email' => $this->email,
            'password' => $this->password,
        ]);

        $service = app(AuthenticationService::class);
        $result = $service->authenticate($request);

        if ($result['success']) {
            if (config('auth.enable_user_licenses') && $result['license']) {
                session()->put('license_id', $result['license']->id);
            }

            return redirect()->intended(route('filament.pages.dashboard'));
        }

        $this->addError('email', 'These credentials do not match our records.');
    }

    public function render()
    {
        return view('filament.auth.login');
    }
}
