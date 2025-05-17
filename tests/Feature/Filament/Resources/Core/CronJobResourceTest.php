<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\CronJob;
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

test('can list cron jobs', function (): void {
    $response = actingAs($this->admin)
        ->get(route('filament.admin.resources.core.cron-jobs.index'));

    $response->assertSuccessful();
});

test('can create cron job', function (): void {
    $cronJobData = [
        'name' => 'Test Cron Job',
        'command' => 'test:command',
        'schedule' => '* * * * *',
        'description' => 'Test cron job description',
        'is_active' => true,
    ];

    $response = actingAs($this->admin)
        ->post(route('filament.admin.resources.core.cron-jobs.create'), $cronJobData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('cron_jobs')->where([
        'name' => 'Test Cron Job',
        'command' => 'test:command',
        'schedule' => '* * * * *',
        'description' => 'Test cron job description',
        'is_active' => true,
    ])->exists())->toBeTrue();
});

test('can edit cron job', function (): void {
    $cronJob = CronJob::factory()->create();

    $response = actingAs($this->admin)
        ->get(route('filament.admin.resources.core.cron-jobs.edit', ['record' => $cronJob]));

    $response->assertSuccessful();
});

test('can update cron job', function (): void {
    $cronJob = CronJob::factory()->create();
    $updateData = [
        'name' => 'Updated Cron Job',
        'command' => 'updated:command',
        'schedule' => '0 * * * *',
        'description' => 'Updated cron job description',
        'is_active' => false,
    ];

    $response = actingAs($this->admin)
        ->put(route('filament.admin.resources.core.cron-jobs.update', ['record' => $cronJob]), $updateData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('cron_jobs')->where([
        'id' => $cronJob->id,
        'name' => 'Updated Cron Job',
        'command' => 'updated:command',
        'schedule' => '0 * * * *',
        'description' => 'Updated cron job description',
        'is_active' => false,
    ])->exists())->toBeTrue();
});

test('can delete cron job', function (): void {
    $cronJob = CronJob::factory()->create();

    $response = actingAs($this->admin)
        ->delete(route('filament.admin.resources.core.cron-jobs.delete', ['record' => $cronJob]));

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('cron_jobs')->where('id', $cronJob->id)->exists())->toBeFalse();
});

test('can run cron job', function (): void {
    $cronJob = CronJob::factory()->create();

    $response = actingAs($this->admin)
        ->post(route('filament.admin.resources.core.cron-jobs.run', ['record' => $cronJob]));

    $response->assertSuccessful();
});

test('cron job resource has required form fields', function (): void {
    $resource = new App\Filament\Resources\Core\CronJobResource();
    $form = $resource->form(new Filament\Forms\Form());

    expect($form->hasComponent('name', 'text'))->toBeTrue();
    expect($form->hasComponent('command', 'text'))->toBeTrue();
    expect($form->hasComponent('schedule', 'text'))->toBeTrue();
    expect($form->hasComponent('description', 'textarea'))->toBeTrue();
    expect($form->hasComponent('is_active', 'toggle'))->toBeTrue();
});

test('cron job resource has required table columns', function (): void {
    $resource = new App\Filament\Resources\Core\CronJobResource();
    $table = $resource->table(new Filament\Tables\Table());

    expect($table->hasColumn('name', 'text'))->toBeTrue();
    expect($table->hasColumn('command', 'text'))->toBeTrue();
    expect($table->hasColumn('schedule', 'text'))->toBeTrue();
    expect($table->hasColumn('is_active', 'boolean'))->toBeTrue();
    expect($table->hasColumn('created_at', 'date'))->toBeTrue();
});

test('cron job resource has required actions', function (): void {
    $resource = new App\Filament\Resources\Core\CronJobResource();
    $table = $resource->table(new Filament\Tables\Table());

    $actions = $table->getActions();
    expect($actions)->toHaveKey('edit');
    expect($actions)->toHaveKey('delete');
    expect($actions)->toHaveKey('run');
});
