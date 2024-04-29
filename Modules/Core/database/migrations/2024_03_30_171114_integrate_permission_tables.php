<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\App\Helpers\CommonMigrationColumns;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('description')->after('guard_name')->nullable(true);
            CommonMigrationColumns::timestamps($table, false, true);

            $table->unique(['name', 'guard_name'], 'permissions_UN');

            DB::statement(
                "ALTER TABLE permissions
                add COLUMN `connection_name` varchar(50) as (regexp_substr(`name`, '^\\\\w+')) stored,
                add COLUMN `table_name` varchar(50) as (replace(regexp_substr(`name`, '\\\\.\\\\w+\\\\.'), '.', '')) stored,
                ADD INDEX permissions_ref_IDX (connection_name, table_name),
                add constraint permissions_name_CHECK CHECK (REGEXP_INSTR(`name`, '^\\\\w+\\\\.\\\\w+\\\\.\\\\w+$') = 1);",
            );
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->string('description')->after('guard_name')->nullable(true);
            CommonMigrationColumns::timestamps($table, false, true, true);
        });

        Schema::table('model_has_permissions', function (Blueprint $table): void {
            CommonMigrationColumns::timestamps($table, true);
        });

        Schema::table('model_has_roles', function (Blueprint $table): void {
            CommonMigrationColumns::timestamps($table, true);
        });

        Schema::table('role_has_permissions', function (Blueprint $table): void {
            CommonMigrationColumns::timestamps($table, true);
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropColumn('description');
            CommonMigrationColumns::dropTimestamps($table, false, true);

            $table->dropUnique('permissions_UN');

            DB::statement(
                "ALTER TABLE permissions
                drop COLUMN `connection_name` varchar(50) as (regexp_substr(`name`, '^\\\\w+')) stored,
                drop COLUMN `table_name` varchar(50) as (replace(regexp_substr(`name`, '\\\\.\\\\w+\\\\.'), '.', '')) stored,
                drop INDEX permissions_ref_IDX (connection_name, table_name),
                drop constraint permissions_name_CHECK CHECK (REGEXP_INSTR(`name`, '^\\\\w+\\\\.\\\\w+\\\\.\\\\w+$') = 1);",
            );
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('description');
            CommonMigrationColumns::dropTimestamps($table, false, true, true);
        });

        Schema::table('model_has_permissions', function (Blueprint $table): void {
            CommonMigrationColumns::dropTimestamps($table, true);
        });

        Schema::table('model_has_roles', function (Blueprint $table): void {
            CommonMigrationColumns::dropTimestamps($table, true);
        });

        Schema::table('role_has_permissions', function (Blueprint $table): void {
            CommonMigrationColumns::dropTimestamps($table, true);
        });
    }
};
