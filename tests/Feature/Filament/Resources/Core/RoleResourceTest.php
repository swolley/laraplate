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

test('can list roles', function (): void {
    $response = actingAs($this->admin)
        ->get(route('filament.admin.resources.core.roles.index'));

    $response->assertSuccessful();
});

test('can create role', function (): void {
    $roleData = [
        'name' => 'Test Role',
        'description' => 'Test role description',
    ];

    $response = actingAs($this->admin)
        ->post(route('filament.admin.resources.core.roles.create'), $roleData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('roles')->where([
        'name' => 'Test Role',
        'description' => 'Test role description',
    ])->exists())->toBeTrue();
});

test('can edit role', function (): void {
    $role = Role::factory()->create();

    $response = actingAs($this->admin)
        ->get(route('filament.admin.resources.core.roles.edit', ['record' => $role]));

    $response->assertSuccessful();
});

test('can update role', function (): void {
    $role = Role::factory()->create();
    $updateData = [
        'name' => 'Updated Role',
        'description' => 'Updated role description',
    ];

    $response = actingAs($this->admin)
        ->put(route('filament.admin.resources.core.roles.update', ['record' => $role]), $updateData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('roles')->where([
        'id' => $role->id,
        'name' => 'Updated Role',
        'description' => 'Updated role description',
    ])->exists())->toBeTrue();
});

test('can delete role', function (): void {
    $role = Role::factory()->create();

    $response = actingAs($this->admin)
        ->delete(route('filament.admin.resources.core.roles.delete', ['record' => $role]));

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('roles')->where('id', $role->id)->exists())->toBeFalse();
});

test('role resource has required form fields', function (): void {
    $resource = new App\Filament\Resources\Core\RoleResource();
    $form = $resource->form(new Filament\Forms\Form());

    expect($form->hasComponent('name', 'text'))->toBeTrue();
    expect($form->hasComponent('description', 'textarea'))->toBeTrue();
    expect($form->hasComponent('permissions', 'select'))->toBeTrue();
});

test('role resource has required table columns', function (): void {
    $resource = new App\Filament\Resources\Core\RoleResource();
    $table = $resource->table(new Filament\Tables\Table());

    expect($table->hasColumn('name', 'text'))->toBeTrue();
    expect($table->hasColumn('description', 'text'))->toBeTrue();
    expect($table->hasColumn('permissions.name', 'text'))->toBeTrue();
    expect($table->hasColumn('created_at', 'date'))->toBeTrue();
});

test('role resource has required actions', function (): void {
    $resource = new App\Filament\Resources\Core\RoleResource();
    $table = $resource->table(new Filament\Tables\Table());

    $actions = $table->getActions();
    expect($actions)->toHaveKey('edit');
    expect($actions)->toHaveKey('delete');
});
