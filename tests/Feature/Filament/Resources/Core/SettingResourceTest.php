<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Core;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Tests\TestCase;

class SettingResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $adminRole = Role::factory()->create(['name' => 'admin']);
        $this->admin->roles()->attach($adminRole);
    }

    public function test_can_list_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('filament.admin.resources.core.settings.index'));

        $response->assertSuccessful();
    }

    public function test_can_create_setting(): void
    {
        $settingData = [
            'key' => 'test_setting',
            'value' => 'test value',
            'type' => 'string',
            'group' => 'test',
            'description' => 'Test setting description',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('filament.admin.resources.core.settings.create'), $settingData);

        $response->assertSuccessful();
        $this->assertDatabaseHas('settings', [
            'key' => 'test_setting',
            'value' => 'test value',
            'type' => 'string',
            'group' => 'test',
            'description' => 'Test setting description',
        ]);
    }

    public function test_can_edit_setting(): void
    {
        $setting = Setting::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('filament.admin.resources.core.settings.edit', ['record' => $setting]));

        $response->assertSuccessful();
    }

    public function test_can_update_setting(): void
    {
        $setting = Setting::factory()->create();
        $updateData = [
            'key' => 'updated_setting',
            'value' => 'updated value',
            'type' => 'string',
            'group' => 'updated',
            'description' => 'Updated setting description',
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('filament.admin.resources.core.settings.update', ['record' => $setting]), $updateData);

        $response->assertSuccessful();
        $this->assertDatabaseHas('settings', [
            'id' => $setting->id,
            'key' => 'updated_setting',
            'value' => 'updated value',
            'type' => 'string',
            'group' => 'updated',
            'description' => 'Updated setting description',
        ]);
    }

    public function test_can_delete_setting(): void
    {
        $setting = Setting::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('filament.admin.resources.core.settings.delete', ['record' => $setting]));

        $response->assertSuccessful();
        $this->assertDatabaseMissing('settings', ['id' => $setting->id]);
    }

    public function test_setting_resource_has_required_form_fields(): void
    {
        $resource = new \App\Filament\Resources\Core\SettingResource();
        $form = $resource->form(new \Filament\Forms\Form());

        $this->assertTrue($form->hasComponent('key', 'text'));
        $this->assertTrue($form->hasComponent('value', 'textarea'));
        $this->assertTrue($form->hasComponent('type', 'select'));
        $this->assertTrue($form->hasComponent('group', 'text'));
        $this->assertTrue($form->hasComponent('description', 'textarea'));
    }

    public function test_setting_resource_has_required_table_columns(): void
    {
        $resource = new \App\Filament\Resources\Core\SettingResource();
        $table = $resource->table(new \Filament\Tables\Table());

        $this->assertTrue($table->hasColumn('key', 'text'));
        $this->assertTrue($table->hasColumn('value', 'text'));
        $this->assertTrue($table->hasColumn('type', 'text'));
        $this->assertTrue($table->hasColumn('group', 'text'));
    }

    public function test_setting_resource_has_required_actions(): void
    {
        $resource = new \App\Filament\Resources\Core\SettingResource();
        $table = $resource->table(new \Filament\Tables\Table());

        $actions = $table->getActions();
        $this->assertArrayHasKey('edit', $actions);
        $this->assertArrayHasKey('delete', $actions);
    }
}
