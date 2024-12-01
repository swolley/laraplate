<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

use function Laravel\Prompts\text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Setting;
use function Laravel\Prompts\password;
use Modules\Core\Casts\ActionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Helpers\HasApprovals;
use Spatie\Permission\PermissionRegistrar;

use Modules\Core\Casts\SettingTypeEnum;

class CoreDatabaseSeeder extends Seeder
{
    private $groups = [];

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
        $already_exists = config('permission.models.permission')::exists();
        $this->command->line("  " . ($already_exists ? 'Updating' : 'Creating') . ' default <fg=cyan;options=bold>permissions</>');
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
        $already_exists = $role_class::exists();
        $this->command->line("  " . ($already_exists ? 'Updating' : 'Creating') . ' default <fg=cyan;options=bold>roles</>');

        $superadmin = 'superadmin';
        $admin = 'admin';
        $guest = 'guest';

        $this->groups[$superadmin] = $role_class::whereName($superadmin)->first();
        $this->groups[$admin] = $role_class::whereName($admin)->first();
        $this->groups[$guest] = $role_class::whereName($guest)->first();

        DB::transaction(function () use ($role_class, $superadmin, $admin, $permission_class, $role_table, $user_table, $guest) {
            if (!$this->groups[$superadmin]) {
                $this->groups[$superadmin] = $this->create($role_class, ['name' => $superadmin, 'locked_at' => now()]);
            }

            if (!$this->groups[$admin]) {
                $this->groups[$admin] = $this->create($role_class, ['name' => $admin, 'locked_at' => now()]);
                // @phpstan-ignore-next-line
                $this->groups[$admin]->givePermissionTo(
                    $permission_class::where('name', 'like', "$user_table.%")
                        ->orWhere('name', 'like', "$role_table.%")
                        ->orWhere('name', 'like', '%.' . ActionEnum::SELECT->value)
                        ->get()
                );
                $this->command->line("    - $admin created");
            } else {
                $this->command->line("    - $admin already exists");
            }

            if (!$this->groups[$guest]) {
                $this->groups[$guest] = $this->create($role_class, ['name' => $guest, 'locked_at' => now()]);
                // @phpstan-ignore-next-line
                $this->groups[$guest]->givePermissionTo(
                    $permission_class::where('name', 'like', "$user_table.%")
                        ->orWhere('name', 'like', "$role_table.%")
                        ->orWhere('name', 'like', '%.' . ActionEnum::SELECT->value)
                        ->get()
                );
                $this->command->line("    - $guest created");
            } else {
                $this->command->line("    - $guest already exists");
            }
        });
    }

    private function defaultUsers(): void
    {
        $user_class = user_class();
        $already_exists = $user_class::exists();
        $this->command->line("  " . ($already_exists ? 'Updating' : 'Creating') . ' default <fg=cyan;options=bold>users</>');

        $root = 'root';
        $admin = 'admin';
        $anonymous = 'anonymous';

        DB::transaction(function () use ($admin, $root, $anonymous, $user_class) {
            if (!$user_class::whereName($root)->exists()) {
                $email = text("Please specify a $root user email", required: true, validate: fn(string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Please type a valid email');
                $password = password("Please specify a $root user password", required: true);
                password("Please confirm the password", required: true, validate: fn(string $value) => $password !== $value ? 'Passwords don\'t match' : null);
                $root_user = $this->create($user_class, [
                    'name' => $root,
                    'username' => $root,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'locked_at' => now(),
                ]);
                // @phpstan-ignore-next-line
                $root_user->assignRole($this->groups['superadmin']);
                $this->command->line("    - $root created");
            } else {
                $this->command->line("    - $root already exists");
            }

            if (!$user_class::whereName($admin)->exists()) {
                $email = text("Please specify a $admin user email", required: true, validate: fn(string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Please type a valid email');
                $password = password("Please specify a $admin user password", required: true);
                password("Please confirm the password", required: true, validate: fn(string $value) => $password !== $value ? 'Passwords don\'t match' : null);
                $admin_user = $this->create($user_class, [
                    'name' => $admin,
                    'username' => $admin,
                    'email' => $email,
                    'password' => Hash::make(config('app.name')),
                    'email_verified_at' => now(),
                ]);
                // @phpstan-ignore-next-line
                $admin_user->assignRole($this->groups[$admin]);
                $this->command->line("    - $admin created");
            } else {
                $this->command->line("    - $admin already exists");
            }

            if (!$user_class::whereName($anonymous)->exists()) {
                $anonymous_user = $this->create($user_class, [
                    'name' => $anonymous,
                    'username' => $anonymous,
                    'email' => "$anonymous@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                    'password' => Hash::make(config('app.name')),
                    'email_verified_at' => now(),
                ]);
                // @phpstan-ignore-next-line
                $anonymous_user->assignRole($this->groups['guest']);
                $this->command->line("    - $anonymous created");
            } else {
                $this->command->line("    - $anonymous already exists");
            }
        });
    }

    private function defaultSettings(): void
    {
        $defaultLanguage = 'defaultLanguage';
        $pagination = 'pagination';
        $maxConcurrentSessions = 'maxConcurrentSessions';
        $already_exists = Setting::query()->exists();
        $this->command->line("  " . ($already_exists ? 'Updating' : 'Creating') . ' default <fg=cyan;options=bold>settings</>');

        DB::transaction(function () use ($defaultLanguage, $pagination, $maxConcurrentSessions) {
            if (!Setting::query()->where('name', $defaultLanguage)->exists()) {
                $this->create(Setting::class, [
                    'name' => $defaultLanguage,
                    'value' => config('app.locale'),
                    'type' => SettingTypeEnum::STRING,
                    'group_name' => 'base',
                    'description' => 'Lingua default',
                ]);
                $this->command->line("    - $defaultLanguage created");
            } else {
                $this->command->line("    - $defaultLanguage already exists");
            }

            if (!Setting::query()->where('name', $pagination)->exists()) {
                $this->create(Setting::class, [
                    'name' => $pagination,
                    'value' => 20,
                    'type' => SettingTypeEnum::INTEGER,
                    'group_name' => 'base',
                    'description' => 'Paginazione default chiamate',
                ]);
                $this->command->line("    - $pagination created");
            } else {
                $this->command->line("    - $pagination already exists");
            }

            if (!Setting::query()->where('name', $maxConcurrentSessions)->exists()) {
                $this->create(Setting::class, [
                    'name' => $maxConcurrentSessions,
                    'value' => PHP_INT_MAX,
                    'type' => SettingTypeEnum::INTEGER,
                    'group_name' => 'base',
                    'description' => 'Numero massimo sessioni simultanee',
                ]);
                $this->command->line("    - $maxConcurrentSessions created");
            } else {
                $this->command->line("    - $maxConcurrentSessions already exists");
            }

            // ModuleDatabaseActivator::seedBackendModules();
        });
    }

    private function defaultCrons(): void
    {
        $clearUserAssignedLicenses = 'clearUserAssignedLicenses';
        $clearResetTokens = 'clearResetTokens';
        $already_exists = CronJob::query()->exists();
        $this->command->line("  " . ($already_exists ? 'Updating' : 'Creating') . ' default <fg=cyan;options=bold>cron jobs</>');

        DB::transaction(function () use ($clearUserAssignedLicenses, $clearResetTokens) {
            if (!CronJob::query()->where('name', $clearUserAssignedLicenses)->exists()) {
                $this->create(CronJob::class, [
                    'name' => $clearUserAssignedLicenses,
                    'command' => 'auth:clear-licenses',
                    'parameters' => [],
                    'schedule' => '@midnight',
                    'description' => 'Resetta assegnazione licenze login a utenti',
                    'is_active' => config('core.enable_user_licenses'),
                ]);
                $this->command->line("    - $clearUserAssignedLicenses created");
            } else {
                $this->command->line("    - $clearUserAssignedLicenses already exists");
            }

            if (!CronJob::query()->where('name', $clearResetTokens)->exists()) {
                $this->create(CronJob::class, [
                    'name' => $clearResetTokens,
                    'command' => 'auth:clear-resets',
                    'parameters' => [],
                    'schedule' => '*/4 * * * *',
                    'description' => 'Rimuove reset password tokens scaduti',
                    'is_active' => true,
                ]);
                $this->command->line("    - $clearResetTokens created");
            } else {
                $this->command->line("    - $clearResetTokens already exists");
            }
        });
    }

    /** 
     * @param class-string $class 
     */
    private function create(string $class, array $attributes): Model
    {
        $model = $class::make($attributes);
        if (class_uses_trait($model, HasApprovals::class)) $model->setForcedApprovalUpdate(true);
        $model->save();
        return $model;
    }
}
