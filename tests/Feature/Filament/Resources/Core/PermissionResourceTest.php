<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\Permission;
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

test('can list permissions', function (): void {
    $response = actingAs($this->admin)
        ->get(route('filament.admin.resources.core.permissions.index'));

    $response->assertSuccessful();
});

test('can create permission', function (): void {
    $permissionData = [
        'name' => 'test.permission',
        'description' => 'Test permission description',
    ];

    $response = actingAs($this->admin)
        ->post(route('filament.admin.resources.core.permissions.create'), $permissionData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('permissions')->where([
        'name' => 'test.permission',
        'description' => 'Test permission description',
    ])->exists())->toBeTrue();
});

test('can edit permission', function (): void {
    $permission = Permission::factory()->create();

    $response = actingAs($this->admin)
        ->get(route('filament.admin.resources.core.permissions.edit', ['record' => $permission]));

    $response->assertSuccessful();
});

test('can update permission', function (): void {
    $permission = Permission::factory()->create();
    $updateData = [
        'name' => 'updated.permission',
        'description' => 'Updated permission description',
    ];

    $response = actingAs($this->admin)
        ->put(route('filament.admin.resources.core.permissions.update', ['record' => $permission]), $updateData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('permissions')->where([
        'id' => $permission->id,
        'name' => 'updated.permission',
        'description' => 'Updated permission description',
    ])->exists())->toBeTrue();
});

test('can delete permission', function (): void {
    $permission = Permission::factory()->create();

    $response = actingAs($this->admin)
        ->delete(route('filament.admin.resources.core.permissions.delete', ['record' => $permission]));

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('permissions')->where('id', $permission->id)->exists())->toBeFalse();
});

test('permission resource has required form fields', function (): void {
    $resource = new App\Filament\Resources\Core\PermissionResource();
    $form = $resource->form(new Filament\Forms\Form());

    expect($form->hasComponent('name', 'text'))->toBeTrue();
    expect($form->hasComponent('description', 'textarea'))->toBeTrue();
});

test('permission resource has required table columns', function (): void {
    $resource = new App\Filament\Resources\Core\PermissionResource();
    $table = $resource->table(new Filament\Tables\Table());

    expect($table->hasColumn('name', 'text'))->toBeTrue();
    expect($table->hasColumn('description', 'text'))->toBeTrue();
    expect($table->hasColumn('created_at', 'date'))->toBeTrue();
});

test('permission resource has required actions', function (): void {
    $resource = new App\Filament\Resources\Core\PermissionResource();
    $table = $resource->table(new Filament\Tables\Table());

    $actions = $table->getActions();
    expect($actions)->toHaveKey('edit');
    expect($actions)->toHaveKey('delete');
});
