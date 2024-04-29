<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Console;

use function Laravel\Prompts\confirm;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Locking\HasOptimisticLocking;

class ModelLockingRefreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lock:refresh { --quiet: prevent output }';

    /**
     * The console command description.
     */
    protected $description = 'Dynamically generate missing migrations for locking functionalities. <comment>(Modules\Core)</comment>';

    private bool $quiet_mode = false;
    private bool $changes = false;

    /**
     * @var string[]
     */
    private array $models_blacklist = [];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->quiet_mode = $this->option('quiet');

        $all_models = models();
        $parental_class = "Parental\HasParent";
        $this->changes = false;

        foreach ($all_models as $model) {
            $need_bypass = $this->checkIfBlacklisted($model);

            if ($need_bypass) {
                if (!$this->quiet_mode) {
                    $this->line("Bypassing '{$model}' class");
                }

                continue;
            }

            /** @var Model */
            $instance = new $model();
            $table = $instance->getTable();

            if (in_array($parental_class, class_uses($instance), true)) {
                continue;
            }

            $this->optimisticLockingCheck($instance, $model, $table);
            $this->lockableCheck($instance, $model, $table);
        }

        if (!$this->changes && !$this->quiet_mode) {
            $this->info('No changes needed');
        }
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

    private function optimisticLockingCheck(Model $instance, string $model, string $table): void
    {
        $optimistick_locking_class = HasOptimisticLocking::class;
        $optimistic_locking_column = $instance->lockVersionColumn();
        $has_optimistic_locking = class_uses_trait($instance, $optimistick_locking_class);

        $has_optimistic_locking_column = Schema::hasColumn($table, $optimistic_locking_column);

        if ($has_optimistic_locking && !$has_optimistic_locking) {
            if ($this->askConfirmForOperation(
                "Model {$model} doesn't use optimistic locking but column {$optimistic_locking_column} found. Would you like to remove it from the schema?",
                $model,
                fn () => $this->call('lock:optimistic-remove', ['model' => $model]),
            )) {
                $this->changes = true;
            }
        } elseif ($has_optimistic_locking && !$has_optimistic_locking_column) {
            if ($this->askConfirmForOperation(
                "Model {$model} uses optimistic locking but column {$optimistic_locking_column} is missing. Would you like to create it into the schema?",
                $model,
                fn () => $this->call('lock:optimistic-add', ['model' => $model]),
            )) {
                $this->changes = true;
            }
        }
    }

    private function lockableCheck(Model $instance, string $model, string $table): void
    {
        $locked_class = HasLocks::class;
        $lock_at_column = $instance->lockedAtColumn();
        $lock_by_column = $instance->lockedByColumn();
        $has_locking = class_uses_trait($instance, $locked_class);

        $has_locked_at_column = Schema::hasColumn($table, $lock_at_column);
        $has_locked_by_column = Schema::hasColumn($table, $lock_by_column);

        if (($has_locked_at_column || $has_locked_by_column) && !$has_locking) {
            if ($this->askConfirmForOperation(
                "Model {$model} doesn't use locks but column {$lock_at_column} found. Would you like to remove it from the schema?",
                $model,
                fn () => $this->call('lock:remove', ['model' => $model]),
            )) {
                $this->changes = true;
            }
        } elseif ($has_locking && (!$has_locked_at_column || !$has_locked_by_column)) {
            if ($this->askConfirmForOperation(
                "Model {$model} uses locks but column {$lock_at_column} is missing. Would you like to create it into the schema?",
                $model,
                fn () => $this->call('lock:add', ['model' => $model]),
            )) {
                $this->changes = true;
            }
        }
    }

    private function askConfirmForOperation(string $confirmText, string $model, callable $operation): bool
    {
        if (confirm($confirmText)) {
            $operation();

            return true;
        }

        if (!$this->quiet_mode) {
            $this->line("Ignoring '{$model}' class");
        }

        return false;
    }
}
