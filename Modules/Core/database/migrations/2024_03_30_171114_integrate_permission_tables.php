<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Helpers\CommonMigrationColumns;

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

            $connection = DB::connection();
            if ($connection->getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE permissions ADD COLUMN connection_name VARCHAR(50) GENERATED ALWAYS AS (regexp_replace(regexp_replace(name, '\\.\\w+\\.\\w+$', ''), '\\.', '')) STORED");
                DB::statement("ALTER TABLE permissions ADD COLUMN table_name VARCHAR(50) GENERATED ALWAYS AS (regexp_replace(regexp_replace(name, '^\\w+\\.', ''), '\\.\\w+$', '')) STORED");
                DB::statement("CREATE INDEX permissions_ref_IDX ON permissions (connection_name, table_name)");
                DB::statement("ALTER TABLE permissions ADD CONSTRAINT permissions_name_CHECK CHECK (name ~ '^\\w+\\.\\w+\\.\\w+$')");
            } elseif (in_array($connection->getDriverName(), ['mysql', 'mariadb'])) {
                DB::statement("ALTER TABLE permissions ADD COLUMN connection_name VARCHAR(50) AS (regexp_substr(name, '^\\\\w+')) STORED");
                DB::statement("ALTER TABLE permissions ADD COLUMN table_name VARCHAR(50) AS (replace(regexp_substr(name, '\\\\.\\\\w+\\\\.'), '.', '')) STORED");
                DB::statement("CREATE INDEX permissions_ref_IDX ON permissions (connection_name, table_name)");
                DB::statement("ALTER TABLE permissions ADD CONSTRAINT permissions_name_CHECK CHECK (REGEXP_INSTR(name, '^\\\\w+\\\\.\\\\w+\\\\.\\\\w+$') = 1)");
            } else if ($connection->getDriverName() === 'sqlite') {
                DB::statement("ALTER TABLE permissions ADD COLUMN connection_name TEXT AS (regexp_replace(regexp_replace(name, '\\.\\w+\\.\\w+$', ''), '\\.', '')) STORED");
                DB::statement("ALTER TABLE permissions ADD COLUMN table_name TEXT AS (regexp_replace(regexp_replace(name, '^\\w+\\.', ''), '\\.\\w+$', '')) STORED");
                DB::statement("CREATE INDEX permissions_ref_IDX ON permissions (connection_name, table_name)");
            } else {
                throw new \Exception('Unsupported database driver');
            }
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
                drop COLUMN `connection_name`,
                drop COLUMN `table_name`,
                drop INDEX permissions_ref_IDX,
                drop constraint permissions_name_CHECK;"
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
