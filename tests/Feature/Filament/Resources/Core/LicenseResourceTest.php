<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\License;
use Modules\Core\Models\Role;
use Tests\TestCase;

uses(TestCase::class);
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($adminRole);
});

test('can list licenses', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('filament.admin.resources.core.licenses.index'));

    $response->assertSuccessful();
});

test('can create license', function (): void {
    $licenseData = [
        'name' => 'Test License',
        'key' => 'test-license-key',
        'domain' => 'https://example.com',
        'email' => 'test@example.com',
        'company' => 'Test Company',
        'phone' => '+1234567890',
        'address' => '123 Test St',
        'city' => 'Test City',
        'state' => 'Test State',
        'zip' => '12345',
        'country' => 'Test Country',
        'expires_at' => now()->addYear(),
        'is_active' => true,
        'notes' => 'Test license notes',
    ];

    $response = $this->actingAs($this->admin)
        ->post(route('filament.admin.resources.core.licenses.create'), $licenseData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('licenses')->where([
        'name' => 'Test License',
        'key' => 'test-license-key',
        'domain' => 'https://example.com',
        'email' => 'test@example.com',
        'company' => 'Test Company',
        'phone' => '+1234567890',
        'address' => '123 Test St',
        'city' => 'Test City',
        'state' => 'Test State',
        'zip' => '12345',
        'country' => 'Test Country',
        'is_active' => true,
        'notes' => 'Test license notes',
    ])->exists())->toBeTrue();
});

test('can edit license', function (): void {
    $license = License::factory()->create();

    $response = $this->actingAs($this->admin)
        ->get(route('filament.admin.resources.core.licenses.edit', ['record' => $license]));

    $response->assertSuccessful();
});

test('can update license', function (): void {
    $license = License::factory()->create();
    $updateData = [
        'name' => 'Updated License',
        'key' => 'updated-license-key',
        'domain' => 'https://updated.example.com',
        'email' => 'updated@example.com',
        'company' => 'Updated Company',
        'phone' => '+0987654321',
        'address' => '456 Updated St',
        'city' => 'Updated City',
        'state' => 'Updated State',
        'zip' => '54321',
        'country' => 'Updated Country',
        'expires_at' => now()->addYears(2),
        'is_active' => false,
        'notes' => 'Updated license notes',
    ];

    $response = $this->actingAs($this->admin)
        ->put(route('filament.admin.resources.core.licenses.update', ['record' => $license]), $updateData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('licenses')->where([
        'id' => $license->id,
        'name' => 'Updated License',
        'key' => 'updated-license-key',
        'domain' => 'https://updated.example.com',
        'email' => 'updated@example.com',
        'company' => 'Updated Company',
        'phone' => '+0987654321',
        'address' => '456 Updated St',
        'city' => 'Updated City',
        'state' => 'Updated State',
        'zip' => '54321',
        'country' => 'Updated Country',
        'is_active' => false,
        'notes' => 'Updated license notes',
    ])->exists())->toBeTrue();
});

test('can delete license', function (): void {
    $license = License::factory()->create();

    $response = $this->actingAs($this->admin)
        ->delete(route('filament.admin.resources.core.licenses.delete', ['record' => $license]));

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('licenses')->where('id', $license->id)->exists())->toBeFalse();
});

test('can validate license', function (): void {
    $license = License::factory()->create();

    $response = $this->actingAs($this->admin)
        ->post(route('filament.admin.resources.core.licenses.validate', ['record' => $license]));

    $response->assertSuccessful();
});

test('license resource has required form fields', function (): void {
    $resource = new App\Filament\Resources\Core\LicenseResource();
    $form = $resource->form(new Filament\Forms\Form());

    $fields = [
        'name' => TextInput::class,
        'key' => TextInput::class,
        'domain' => TextInput::class,
        'email' => TextInput::class,
        'company' => TextInput::class,
        'phone' => TextInput::class,
        'address' => TextInput::class,
        'city' => TextInput::class,
        'state' => TextInput::class,
        'zip' => TextInput::class,
        'country' => TextInput::class,
        'expires_at' => DatePicker::class,
        'is_active' => Toggle::class,
        'notes' => Textarea::class,
    ];

    foreach ($fields as $field => $component) {
        $formComponent = $form->getComponent($field);
        expect($formComponent)->toBeInstanceOf($component);
    }
});

test('license resource has required table columns', function (): void {
    $resource = new App\Filament\Resources\Core\LicenseResource();
    $table = $resource->table(new Filament\Tables\Table());

    $columns = [
        'name' => TextColumn::class,
        'domain' => TextColumn::class,
        'email' => TextColumn::class,
        'company' => TextColumn::class,
        'expires_at' => TextColumn::class,
        'is_active' => IconColumn::class,
    ];

    foreach ($columns as $column => $type) {
        $tableColumn = $table->getColumn($column);
        expect($tableColumn)->toBeInstanceOf($type);
    }
});

test('license resource has required actions', function (): void {
    $resource = new App\Filament\Resources\Core\LicenseResource();
    $table = $resource->table(new Filament\Tables\Table());

    $actions = $table->getActions();
    expect($actions)->toHaveKey('edit');
    expect($actions)->toHaveKey('delete');
    expect($actions)->toHaveKey('validate');
});
