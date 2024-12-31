<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Setting;
use Illuminate\Support\Facades\DB;
use Modules\Core\Casts\ActionEnum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Helpers\HasSeedersUtils;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Eloquent\Collection;

class CoreDatabaseSeeder extends Seeder
{
    use HasSeedersUtils;

    /**
     * @var Collection<string, Role>
     */
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
            $this->defaultUsers();
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
        $user_class = user_class();
        $role_class = config('permission.models.role');
        $permission_class = config('permission.models.permission');
        $role_table = (new $role_class)->getTable();
        $user_table = (new $user_class)->getTable();
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
                $authorized_permissions = $permission_class::where(function ($query) use ($user_table, $role_table) {
                    $query->whereIn('table_name', [$user_table, $role_table])
                        ->orWhere('name', 'like', '%.' . ActionEnum::SELECT->value);
                })
                    ->orWhereNot('name', 'like', '%.' . ActionEnum::LOCK->value)
                    ->get();
                // @phpstan-ignore-next-line
                $permission->givePermissionTo($authorized_permissions);
                $this->command->line("    - $name <fg=green>created</>");
            } else {
                $this->command->line("    - $name already exists");
            }

            $name = 'guest';
            if (!$this->groups->has($name)) {
                $permission = $this->create($role_class, ['name' => $name, 'locked_at' => now()]);
                $this->groups->put($name, $permission);
                $authorized_permissions = $permission_class::where('name', 'like', '%.' . ActionEnum::SELECT->value)
                    ->whereNotIn('table_name', [
                        'versions',
                        'user_grid_configs',
                        'modifications',
                        'cron_jobs',
                    ])
                    ->get();
                // @phpstan-ignore-next-line
                $permission->givePermissionTo($authorized_permissions);
                $this->command->line("    - $name <fg=green>created</>");
            } else {
                $this->command->line("    - $name already exists");
            }
        });
    }

    private function defaultUsers(): void
    {
        $user_class = user_class();

        $this->logOperation($user_class);

        $anonymous = 'anonymous';
        if (!$user_class::whereName($anonymous)->exists()) {
            $anonymous_user = $this->create($user_class, [
                'name' => $anonymous,
                'username' => $anonymous,
                'email' => "$anonymous@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                'password' => Hash::make(config('app.name')),
                'email_verified_at' => now(),
            ]);
            // @phpstan-ignore-next-line
            $anonymous_user->assignRole($this->groups->get('guest'));
            $this->command->line("    - $anonymous <fg=green>created</>");
        } else {
            $this->command->line("    - $anonymous already exists");
        }

        $superadmin = 'superadmin';
        if (!$user_class::whereHas('roles', fn($query) => $query->where('name', $superadmin))->exists()) {
            $this->command->line("    - Creation of a user related to '$superadmin' role is <fg=yellow>strongly suggested</>");
        }

        $admin = 'admin';
        if (!$user_class::whereHas('roles', fn($query) => $query->where('name', $admin))->exists()) {
            $this->command->line("    - Creation of a user related to '$admin' role is <fg=yellow>strongly suggested</>");
        }
    }

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
                $this->command->line("    - $name <fg=green>created</>");
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
                $this->command->line("    - $name <fg=green>created</>");
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
                $this->command->line("    - $name <fg=green>created</>");
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
                $this->command->line("    - $name <fg=green>created</>");
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
                $this->command->line("    - $name <fg=green>created</>");
            } else {
                $this->command->line("    - $name already exists");
            }
        });
    }
}
