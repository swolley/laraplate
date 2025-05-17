<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\Role;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($adminRole);
});

test('can list users', function (): void {
    $response = actingAs($this->admin)
        ->get(route('filament.admin.resources.core.users.index'));

    $response->assertSuccessful();
});

test('can create user', function (): void {
    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    $response = actingAs($this->admin)
        ->post(route('filament.admin.resources.core.users.create'), $userData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('users')->where([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ])->exists())->toBeTrue();
});

test('can edit user', function (): void {
    $user = User::factory()->create();

    $response = actingAs($this->admin)
        ->get(route('filament.admin.resources.core.users.edit', ['record' => $user]));

    $response->assertSuccessful();
});

test('can update user', function (): void {
    $user = User::factory()->create();
    $updateData = [
        'name' => 'Updated User',
        'email' => 'updated@example.com',
        'password' => 'new_password',
        'password_confirmation' => 'new_password',
    ];

    $response = actingAs($this->admin)
        ->put(route('filament.admin.resources.core.users.update', ['record' => $user]), $updateData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('users')->where([
        'id' => $user->id,
        'name' => 'Updated User',
        'email' => 'updated@example.com',
    ])->exists())->toBeTrue();
});

test('can delete user', function (): void {
    $user = User::factory()->create();

    $response = actingAs($this->admin)
        ->delete(route('filament.admin.resources.core.users.delete', ['record' => $user]));

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->exists())->toBeFalse();
});

test('user resource has required form fields', function (): void {
    $resource = new App\Filament\Resources\Core\UserResource();
    $form = $resource->form(new Filament\Forms\Form());

    expect($form->hasComponent('name', 'text'))->toBeTrue();
    expect($form->hasComponent('email', 'email'))->toBeTrue();
    expect($form->hasComponent('password', 'password'))->toBeTrue();
    expect($form->hasComponent('password_confirmation', 'password'))->toBeTrue();
});

test('user resource has required table columns', function (): void {
    $resource = new App\Filament\Resources\Core\UserResource();
    $table = $resource->table(new Filament\Tables\Table());

    expect($table->hasColumn('name', 'text'))->toBeTrue();
    expect($table->hasColumn('email', 'text'))->toBeTrue();
    expect($table->hasColumn('created_at', 'date'))->toBeTrue();
});

test('user resource has required actions', function (): void {
    $resource = new App\Filament\Resources\Core\UserResource();
    $table = $resource->table(new Filament\Tables\Table());

    $actions = $table->getActions();
    expect($actions)->toHaveKey('edit');
    expect($actions)->toHaveKey('delete');
});
