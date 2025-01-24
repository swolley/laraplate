<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class CommonMigrationColumns
{
    /**
     * Add common timestamp columns to a table
     *
     * @param Blueprint $table The table blueprint
     * @param bool $hasCreateUpdate Add created_at and updated_at columns
     * @param bool $hasSoftDelete Add soft delete functionality
     * @param bool $hasLocks Add locking columns
     * @param bool $hasValidity Add validity period columns
     * @param bool $isValidityRequired Make valid_from required
     * @param bool $addIndices Add performance indices
     * @throws \InvalidArgumentException When invalid parameter combination
     */
    public static function timestamps(
        Blueprint $table, 
        bool $hasCreateUpdate = true, 
        bool $hasSoftDelete = false, 
        bool $hasLocks = false, 
        bool $hasValidity = false, 
        bool $isValidityRequired = true
    ): void {
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
            $valid_from_column = HasValidity::validFromKey();
            $valid_to_column = HasValidity::validToKey();
            if ($isValidityRequired) {
                $table->datetime($valid_from_column)->nullable(false)->useCurrent();
            } else {
                $table->datetime($valid_from_column)->nullable(true);
            }
            $table->datetime($valid_to_column)->nullable(true);

            $table->index([ $valid_from_column, $valid_to_column], $table->getTable() . '_validity_range');
        }
    }

    /**
     * Drop common timestamp columns from a table
     *
     * @param Blueprint $table The table blueprint
     * @param bool $hasCreateUpdate Add created_at and updated_at columns
     * @param bool $hasSoftDelete Add soft delete functionality
     * @param bool $hasLocks Add locking columns
     * @param bool $hasValidity Add validity period columns
     */
    public static function dropTimestamps(
        Blueprint $table, 
        bool $hasCreateUpdate = true, 
        bool $hasSoftDelete = false, 
        bool $hasLocks = false, 
        bool $hasValidity = false
    ): void {
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
            $table->dropIndex($table->getTable() . '_validity_range');
            $table->dropColumn(HasValidity::validFromKey());
            $table->dropColumn(HasValidity::validToKey());
        }
    }
}
