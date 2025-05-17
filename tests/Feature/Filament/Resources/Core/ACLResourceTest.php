<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\ACL;
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

test('can list acls', function (): void {
    $response = actingAs($this->admin)
        ->get(route('filament.admin.resources.core.acls.index'));

    $response->assertSuccessful();
});

test('can create acl', function (): void {
    $permission = Permission::factory()->create();
    $aclData = [
        'permission_id' => $permission->id,
        'filters' => ['test' => 'value'],
        'sort' => ['field' => 'created_at', 'direction' => 'desc'],
        'description' => 'Test ACL description',
    ];

    $response = actingAs($this->admin)
        ->post(route('filament.admin.resources.core.acls.create'), $aclData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('acls')->where([
        'permission_id' => $permission->id,
        'filters' => json_encode(['test' => 'value']),
        'sort' => json_encode(['field' => 'created_at', 'direction' => 'desc']),
        'description' => 'Test ACL description',
    ])->exists())->toBeTrue();
});

test('can edit acl', function (): void {
    $acl = ACL::factory()->create();

    $response = actingAs($this->admin)
        ->get(route('filament.admin.resources.core.acls.edit', ['record' => $acl]));

    $response->assertSuccessful();
});

test('can update acl', function (): void {
    $acl = ACL::factory()->create();
    $permission = Permission::factory()->create();
    $updateData = [
        'permission_id' => $permission->id,
        'filters' => ['updated' => 'value'],
        'sort' => ['field' => 'updated_at', 'direction' => 'asc'],
        'description' => 'Updated ACL description',
    ];

    $response = actingAs($this->admin)
        ->put(route('filament.admin.resources.core.acls.update', ['record' => $acl]), $updateData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('acls')->where([
        'id' => $acl->id,
        'permission_id' => $permission->id,
        'filters' => json_encode(['updated' => 'value']),
        'sort' => json_encode(['field' => 'updated_at', 'direction' => 'asc']),
        'description' => 'Updated ACL description',
    ])->exists())->toBeTrue();
});

test('can delete acl', function (): void {
    $acl = ACL::factory()->create();

    $response = actingAs($this->admin)
        ->delete(route('filament.admin.resources.core.acls.delete', ['record' => $acl]));

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('acls')->where('id', $acl->id)->exists())->toBeFalse();
});

test('acl resource has required form fields', function (): void {
    $resource = new App\Filament\Resources\Core\ACLResource();
    $form = $resource->form(new Filament\Forms\Form());

    expect($form->hasComponent('permission_id', 'select'))->toBeTrue();
    expect($form->hasComponent('filters', 'json'))->toBeTrue();
    expect($form->hasComponent('sort', 'json'))->toBeTrue();
    expect($form->hasComponent('description', 'textarea'))->toBeTrue();
});

test('acl resource has required table columns', function (): void {
    $resource = new App\Filament\Resources\Core\ACLResource();
    $table = $resource->table(new Filament\Tables\Table());

    expect($table->hasColumn('permission.name', 'text'))->toBeTrue();
    expect($table->hasColumn('filters', 'json'))->toBeTrue();
    expect($table->hasColumn('sort', 'json'))->toBeTrue();
    expect($table->hasColumn('description', 'text'))->toBeTrue();
    expect($table->hasColumn('created_at', 'date'))->toBeTrue();
});

test('acl resource has required actions', function (): void {
    $resource = new App\Filament\Resources\Core\ACLResource();
    $table = $resource->table(new Filament\Tables\Table());

    $actions = $table->getActions();
    expect($actions)->toHaveKey('edit');
    expect($actions)->toHaveKey('delete');
});
