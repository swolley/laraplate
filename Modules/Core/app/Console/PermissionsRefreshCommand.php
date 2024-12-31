<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Casts\ActionEnum;
use Approval\Traits\RequiresApproval;
use Modules\Core\Helpers\HasValidity;
use Illuminate\Database\Eloquent\SoftDeletes;

class PermissionsRefreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the Permission table with inspected rules <comment>(⛭ Modules\Core)</comment>';

    /**
     * @var string[]
     */
    private array $models_blacklist = [
        'App\\Models\\Version',
        'App\\Models\\Modification',
        'Modules\\Core\\Models\\DynamicEntity',
        'Modules\\Core\\Models\\License',
        'Modules\\Core\\Models\\ModelEmbedding',
        'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $quiet_mode = $this->option('quiet');

        $all_models = models();
        $user_class = user_class();

        $common_permissions = [
            ActionEnum::SELECT,
            ActionEnum::INSERT,
            ActionEnum::LOCK,
            // ActionEnum::UNLOCK,
            ActionEnum::UPDATE,
            ActionEnum::DELETE,
            ActionEnum::FORCE_DELETE,
            // ActionEnum::RESTORE,
            ActionEnum::APPROVE,
            // ActionEnum::DISAPPROVE,
            ActionEnum::PUBLISH,
            // ActionEnum::UNPUBLISH,
        ];
        $changes = false;
        $all_permissions = [];

        $permission_class = config('permission.models.permission');
        $parental_class = "Parental\\HasChildren";

        DB::beginTransaction();

        foreach ($all_models as $model) {
            $need_bypass = $this->checkIfBlacklisted($model);

            if ($need_bypass) {
                if (!$quiet_mode) {
                    $this->line("Bypassing '{$model}' class");
                }

                continue;
            }

            $instance = new $model();

            if (class_uses_trait($instance, $parental_class) && !in_array($parental_class, class_uses($instance), true)) {
                continue;
            }

            $connection = $instance->getConnectionName() ?? 'default';
            $table = $instance->getTable();

            $permission_class::flushEventListeners();
            $found_permissions = $permission_class::where(['connection_name' => $connection, 'table_name' => $table])->pluck('name')->toArray();
            $new_model_suffix = empty($found_permissions) ? " for new model {$model}" : '';

            foreach ($common_permissions as $permission) {
                $permission_name = $connection . '.' . $table . '.' . $permission->value;
                $all_permissions[] = $permission_name;

                // permessi di cancellazione logica
                if ($permission === ActionEnum::DELETE && !class_uses_trait($model, SoftDeletes::class)) {
                    if (in_array($permission_name, $found_permissions, true) && $permission_class::where('name', $permission_name)->forceDelete()) {
                        if (!$quiet_mode) {
                            $this->line("<fg=red>Deleted</> '{$permission_name}' permission");
                        }
                        $changes = true;
                    }

                    continue;
                }

                // permessi di approvazione
                if ($permission === ActionEnum::APPROVE && !class_uses_trait($model, RequiresApproval::class)) {
                    if (in_array($permission_name, $found_permissions, true) && $permission_class::where('name', $permission_name)->forceDelete()) {
                        if (!$quiet_mode) {
                            $this->line("<fg=red>Deleted</> '{$permission_name}' permission");
                        }
                        $changes = true;
                    }

                    continue;
                }

                // permessi di pubblicazione
                if ($permission === ActionEnum::PUBLISH && !class_uses_trait($model, HasValidity::class)) {
                    if (in_array($permission_name, $found_permissions, true) && $permission_class::where('name', $permission_name)->forceDelete()) {
                        if (!$quiet_mode) {
                            $this->line("<fg=red>Deleted</> '{$permission_name}' permission");
                        }
                        $changes = true;
                    }

                    continue;
                }

                if (!in_array($permission_name, $found_permissions, true)) {
                    $query = $permission_class::where('name', $permission_name)->withTrashed();
                    if ($query->exists()) {
                        $query->restore();

                        if (!$quiet_mode) {
                            $this->line("<fg=green>Restored</> '{$permission_name}' permission {$new_model_suffix}");
                        }
                        $changes = true;
                    } else {

                        $permission_class::create([
                            'name' => $permission_name,
                        ]);

                        if (!$quiet_mode) {
                            $this->line("<fg=green>Created</> '{$permission_name}' permission {$new_model_suffix}");
                        }
                        $changes = true;
                    }
                }
            }

            if ($model === $user_class) {
                // solo per gli utenti aggiungo l'impersonificazione
                $permission_name = "{$connection}.{$table}." . ActionEnum::IMPERSONATE->value;
                $all_permissions[] = $permission_name;

                if (!in_array($permission_name, $found_permissions, true)) {
                    $permission_class::updateOrCreate(
                        ['name' => $permission_name],
                        ['name' => $permission_name]
                    );
                    if (!$quiet_mode) {
                        $this->line("<fg=green>Created</> '{$permission_name}' permission {$new_model_suffix}");
                    }
                    $changes = true;
                }
            }
        }

        // mappare classi (commentato perché i modelli creati su file system verrebbero eliminati durante un deploy)
        // Permission::firstOrCreate(['name' => 'map_model'], ['name' => 'map_model']);
        // eliminare cache di un modello (commentato perché da decidere in che modo renderla fattibile)
        // Permission::firstOrCreate(['name' => 'flush_cache'], ['name' => 'flush_cache']);

        $query = $permission_class::whereNotIn('name', $all_permissions);
        $to_be_deleted = $query->pluck('name')->toArray();

        if (!empty($to_be_deleted) && $query->delete()) {
            $changes = true;

            if (!$quiet_mode) {
                foreach ($to_be_deleted as $permission) {
                    $this->info("Deleted '{$permission}' permission");
                }
            }
        }

        if (!$changes && !$quiet_mode) {
            $this->info('No changes needed');
        }

        DB::commit();
    }

    private function checkIfBlacklisted(string $model): bool
    {
        foreach ($this->models_blacklist as $blacklisted) {
            if ($model === $blacklisted || is_subclass_of($model, $blacklisted)) {
                return true;
            }
        }

        return false;
    }
}
