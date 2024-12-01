<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class CommonMigrationColumns
{
    public static function timestamps(Blueprint $table, bool $hasCreateUpdate = true, bool $hasSoftDelete = false, bool $hasLocks = false, bool $hasValidity = false): void
    {
        if ($hasCreateUpdate) {
            $table->timestamp(Model::CREATED_AT)->nullable(false)->useCurrent();
            $table->timestamp(Model::UPDATED_AT)->nullable(false)->useCurrent()->useCurrentOnUpdate();
        }

        if ($hasSoftDelete) {
            $table->softDeletes();
        }

        if ($hasLocks) {
            if ($locked_at_column = app('locked')->lockedAtColumn()) {
                $table->timestamp($locked_at_column)->nullable();
            }
            if ($locked_by_column = app('locked')->lockedByColumn()) {
                $table->timestamp($locked_by_column)->nullable();
            }
        }

        if ($hasValidity) {
            $table->datetime(HasValidity::validFromKey())->nullable(false)->useCurrent();
            $table->datetime(HasValidity::validToKey())->nullable(true);
        }
    }

    public static function dropTimestamps(Blueprint $table, bool $hasCreateUpdate = true, bool $hasSoftDelete = false, bool $hasLocks = false, bool $hasValidity = false): void
    {
        if ($hasCreateUpdate) {
            $table->dropColumn(Model::CREATED_AT);
            $table->dropColumn(Model::UPDATED_AT);
        }

        if ($hasSoftDelete) {
            $table->dropSoftDeletes();
        }

        if ($hasLocks) {
            if ($locking_at_column = app('locked')->lockedAtColumn()) {
                $table->dropColumn($locking_at_column);
            }
            if ($locking_by_column = app('locked')->lockedByColumn()) {
                $table->dropColumn($locking_by_column);
            }
        }

        if ($hasValidity) {
            $table->dropColumn(HasValidity::validFromKey());
            $table->dropColumn(HasValidity::validToKey());
        }
    }
}
