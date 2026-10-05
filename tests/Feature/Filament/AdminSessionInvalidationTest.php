<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

it('sends a session invalidated by a password change back to the panel login', function (): void {
    $role = Role::factory()->create([
        'name' => config('permission.roles.superadmin'),
        'guard_name' => 'web',
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    // A hash stored before the password changed (a reseed does it too): the session
    // middleware logs the user out. The app defines no "login" route, so the redirect
    // must point at the panel's own sign-in page.
    $this->actingAs($user, 'admin')
        ->withSession(['password_hash_admin' => 'stale-password-hash'])
        ->get(Filament::getPanel('admin')->getUrl())
        ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

    $this->assertGuest('admin');
});
