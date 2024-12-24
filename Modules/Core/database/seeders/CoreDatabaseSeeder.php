<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Setting;
use Illuminate\Support\Facades\DB;
use Modules\Core\Casts\ActionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Helpers\HasSeedersUtils;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Eloquent\Collection;

class CoreDatabaseSeeder extends Seeder
{
    use HasSeedersUtils;

    private Collection $groups;

    /**
     * Seed the application's database.
     *
     */
    public function run(): void
    {
        Model::unguarded(function (): void {
            $this->defaultSettings();
            $this->defaultPermissions();
            $this->defaultRoles();
            // $this->defaultUsers();
            $this->defaultCrons();
        });
        $this->command->newLine();
    }

    private function defaultPermissions(): void
    {
        // il comando ha già le transaction
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->logOperation(config('permission.models.permission'));
        Artisan::call('permission:refresh');
        $this->command->line("    - permissions updated");
    }

    private function defaultRoles(): void
    {
        $role_class = config('permission.models.role');
        $user_class = user_class();
        $role_table = (new $role_class)->getTable();
        $user_table = (new $user_class)->getTable();
        $permission_class = config('permission.models.permission');
        $this->logOperation($role_class);


        $this->groups = $role_class::withoutGlobalScopes()->get()->keyBy('name');

        DB::transaction(function () use ($role_class, $permission_class, $role_table, $user_table) {

            $name = 'superadmin';
            if (!$this->groups->has($name)) {
                $this->groups->put($name, $this->create($role_class, ['name' => $name, 'locked_at' => now()]));
            }

            $name = 'admin';
            if (!$this->groups->has($name)) {
                $permission = $this->create($role_class, ['name' => $name, 'locked_at' => now()]);
                $this->groups->put($name, $permission);
                // @phpstan-ignore-next-line
                $permission->givePermissionTo(
                    $permission_class::where('name', 'like', "$user_table.%")
                        ->orWhere('name', 'like', "$role_table.%")
                        ->orWhere('name', 'like', '%.' . ActionEnum::SELECT->value)
                        ->get()
                );
                $this->command->line("    - $name created");
            } else {
                $this->command->line("    - $name already exists");
            }

            $name = 'guest';
            if (!$this->groups->has($name)) {
                $permission = $this->create($role_class, ['name' => $name, 'locked_at' => now()]);
                $this->groups->put($name, $permission);
                // @phpstan-ignore-next-line
                $permission->givePermissionTo(
                    $permission_class::where('name', 'like', "$user_table.%")
                        ->orWhere('name', 'like', "$role_table.%")
                        ->orWhere('name', 'like', '%.' . ActionEnum::SELECT->value)
                        ->get()
                );
                $this->command->line("    - $name created");
            } else {
                $this->command->line("    - $name already exists");
            }
        });
    }

    // private function defaultUsers(): void
    // {
    //     $user_class = user_class();
    //     $already_exists = $user_class::exists();
    //     $this->command->line("  " . ($already_exists ? 'Updating' : 'Creating') . ' default <fg=cyan;options=bold>users</>');

    //     Artisan::call('auth:initialize-users');
    // }

    private function defaultSettings(): void
    {
        $this->logOperation(Setting::class);

        DB::transaction(function () {
            $name = 'defaultLanguage';
            if (!Setting::query()->withoutGlobalScopes()->where('name', $name)->exists()) {
                $this->create(Setting::class, [
                    'name' => $name,
                    'value' => config('app.locale'),
                    'type' => SettingTypeEnum::STRING,
                    'group_name' => 'base',
                    'description' => 'Lingua default',
                ]);
                $this->command->line("    - $name created");
            } else {
                $this->command->line("    - $name already exists");
            }

            $name = 'pagination';
            if (!Setting::query()->withoutGlobalScopes()->where('name', $name)->exists()) {
                $this->create(Setting::class, [
                    'name' => $name,
                    'value' => 20,
                    'type' => SettingTypeEnum::INTEGER,
                    'group_name' => 'base',
                    'description' => 'Paginazione default chiamate',
                ]);
                $this->command->line("    - $name created");
            } else {
                $this->command->line("    - $name already exists");
            }

            $name = 'maxConcurrentSessions';
            if (!Setting::query()->withoutGlobalScopes()->where('name', $name)->exists()) {
                $this->create(Setting::class, [
                    'name' => $name,
                    'value' => PHP_INT_MAX,
                    'type' => SettingTypeEnum::INTEGER,
                    'group_name' => 'base',
                    'description' => 'Numero massimo sessioni simultanee',
                ]);
                $this->command->line("    - $name created");
            } else {
                $this->command->line("    - $name already exists");
            }

            // ModuleDatabaseActivator::seedBackendModules();
        });
    }

    private function defaultCrons(): void
    {
        $this->logOperation(CronJob::class);

        DB::transaction(function () {
            $name = 'clearUserAssignedLicenses';
            if (!CronJob::query()->withoutGlobalScopes()->where('name', $name)->exists()) {
                $this->create(CronJob::class, [
                    'name' => $name,
                    'command' => 'auth:clear-licenses',
                    'parameters' => [],
                    'schedule' => '@midnight',
                    'description' => 'Resetta assegnazione licenze login a utenti',
                    'is_active' => config('core.enable_user_licenses'),
                ]);
                $this->command->line("    - $name created");
            } else {
                $this->command->line("    - $name already exists");
            }

            $name = 'clearResetTokens';
            if (!CronJob::query()->withoutGlobalScopes()->where('name', $name)->exists()) {
                $this->create(CronJob::class, [
                    'name' => $name,
                    'command' => 'auth:clear-resets',
                    'parameters' => [],
                    'schedule' => '*/4 * * * *',
                    'description' => 'Rimuove reset password tokens scaduti',
                    'is_active' => true,
                ]);
                $this->command->line("    - $name created");
            } else {
                $this->command->line("    - $name already exists");
            }
        });
    }
}
