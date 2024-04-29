<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Core\App\Models\CronJob;
use Modules\Core\App\Models\Setting;
use Modules\Core\App\Casts\ActionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\App\Helpers\HasApprovals;
use Spatie\Permission\PermissionRegistrar;
use Modules\Core\App\Casts\SettingTypeEnum;

// use Modules\Core\App\Helpers\ModuleDatabaseActivator;

class CoreDatabaseSeeder extends Seeder
{
    private ?Setting $defaultLanguage;

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

        $this->groups['superadmin'] = $role_class::whereName($superadmin)->first();
        $this->groups['admin'] = $role_class::whereName($admin)->first();
        $this->groups['guest'] = $role_class::whereName($guest)->first();

        DB::transaction(function () use ($role_class, $superadmin, $admin, $permission_class, $user_class, $role_table, $user_table, $guest) {
            if (!$this->groups['superadmin']) {
                $this->groups['superadmin'] = $this->create($role_class, ['name' => $superadmin, 'locked_at' => now()]);
                // $this->groups['superadmin']->lock();
            }

            if (!$this->groups['admin']) {
                $this->groups['admin'] = $this->create($role_class, ['name' => $admin, 'locked_at' => now()]);
                $this->groups['admin']->givePermissionTo(
                    $permission_class::where('name', 'like', "$user_table.%")
                        ->orWhere('name', 'like', "$role_table.%")
                        ->orWhere('name', 'like', '%.' . ActionEnum::SELECT->value)
                        ->get()
                );
                // $this->groups['admin']->lock();
            }

            if (!$this->groups['guest']) {
                $this->groups['guest'] = $this->create($role_class, ['name' => $guest, 'locked_at' => now()]);
                $this->groups['guest']->givePermissionTo(
                    $permission_class::where('name', 'like', "$user_table.%")
                        ->orWhere('name', 'like', "$role_table.%")
                        ->orWhere('name', 'like', '%.' . ActionEnum::SELECT->value)
                        ->get()
                );
                // $this->groups['guest']->lock();
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
                $root_user = $this->create($user_class, [
                    'name' => $root,
                    'username' => $root,
                    'email' => 'sviluppo@willbit.com',
                    'password' => Hash::make('WillBit07!!'),
                    // 'lang' => $this->defaultLanguage->value,
                    'email_verified_at' => now(),
                    'locked_at' => now(),
                ]);
                $root_user->assignRole($this->groups['superadmin']);
                // $root_user->lock();
            }

            if (!$user_class::whereName($admin)->exists()) {
                $admin_user = $this->create($user_class, [
                    'name' => $admin,
                    'username' => $admin,
                    'email' => "$admin@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                    'password' => Hash::make(config('app.name')),
                    // 'lang' => $this->defaultLanguage->value,
                    'email_verified_at' => now(),
                ]);
                $admin_user->assignRole($this->groups['admin']);
            }

            if (!$user_class::whereName($anonymous)->exists()) {
                $anonymous_user = $this->create($user_class, [
                    'name' => $anonymous,
                    'username' => $anonymous,
                    'email' => "$anonymous@" . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                    'password' => Hash::make(config('app.name')),
                    // 'lang' => $this->defaultLanguage->value,
                    'email_verified_at' => now(),
                ]);
                $anonymous_user->assignRole($this->groups['guest']);
            }
        });
    }

    private function defaultSettings(): void
    {
        $defaultLanguage = 'defaultLanguage';
        $pagination = 'pagination';
        $already_exists = Setting::exists();
        $this->command->line("  " . ($already_exists ? 'Updating' : 'Creating') . ' default <fg=cyan;options=bold>settings</>');

        DB::transaction(function () use ($defaultLanguage, $pagination) {
            if (!Setting::whereName($defaultLanguage)->exists()) {
                $this->create(Setting::class, [
                    'name' => $defaultLanguage,
                    'value' => 'it',
                    'type' => SettingTypeEnum::STRING,
                    'group_name' => 'base',
                    'description' => 'Lingua default',
                ]);
            }

            if (!Setting::whereName($pagination)->exists()) {
                $this->create(Setting::class, [
                    'name' => $pagination,
                    'value' => 20,
                    'type' => SettingTypeEnum::INTEGER,
                    'group_name' => 'base',
                    'description' => 'Paginazione default chiamate',
                ]);
            }

            // ModuleDatabaseActivator::seedBackendModules();
        });
    }

    private function defaultCrons(): void
    {
        $clearUserAssigneLicenses = 'clearUserAssignedLicenses';
        $clearResetTokens = 'clearResetTokens';
        $already_exists = CronJob::exists();
        $this->command->line("  " . ($already_exists ? 'Updating' : 'Creating') . ' default <fg=cyan;options=bold>cron jobs</>');

        DB::transaction(function () use ($clearUserAssigneLicenses, $clearResetTokens) {
            if (!CronJob::whereName($clearUserAssigneLicenses)->exists()) {
                $this->create(CronJob::class, [
                    'name' => $clearUserAssigneLicenses,
                    'command' => 'auth:clear-licenses',
                    'parameters' => [],
                    'schedule' => '@midnight',
                    'description' => 'Resetta assegnazione licenze login a utenti',
                    'is_active' => config('core.enable_user_licenses'),
                ]);
            }

            if (!CronJob::whereName($clearResetTokens)->exists()) {
                $this->create(CronJob::class, [
                    'name' => $clearResetTokens,
                    'command' => 'auth:clear-resets',
                    'parameters' => [],
                    'schedule' => '*/4 * * * *',
                    'description' => 'Rimuove reset password tokens scaduti',
                    'is_active' => true,
                ]);
            }
        });
    }

    /** @var class-string $class */
    private function create(string $class, array $attributes): Model
    {
        $model = $class::make($attributes);
        if (class_uses_trait($model, HasApprovals::class)) $model->setForcedApprovalUpdate(true);
        $model->save();
        return $model;
    }
}
