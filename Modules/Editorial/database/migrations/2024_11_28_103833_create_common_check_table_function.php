<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        switch (config('database.default')) {
            case 'pgsql':
                DB::statement('CREATE OR REPLACE FUNCTION check_table_name() RETURNS TRIGGER AS $$ DECLARE table_exists BOOLEAN; BEGIN SELECT EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = \'public\' AND tablename = NEW.entity) INTO table_exists; IF NOT table_exists THEN RAISE EXCEPTION \'Table % does not exist\', NEW.entity; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql;');
                break;
            case 'mysql':
                DB::statement('CREATE FUNCTION check_table_exists(table_name VARCHAR(64)) RETURNS BOOLEAN BEGIN DECLARE table_exists INT; SELECT COUNT(*) INTO table_exists FROM information_schema.tables WHERE table_schema = "' . config('database.connections.mysql.database') . '" AND table_name = table_name; RETURN table_exists > 0; END$$');
                break;
            case 'oracle':
                DB::statement("CREATE OR REPLACE FUNCTION check_table_exists(table_name IN VARCHAR2) RETURNS BOOLEAN IS table_exists NUMBER; BEGIN SELECT COUNT(*) INTO table_exists FROM ALL_TABLES WHERE OWNER = '" . config('database.connections.oracle.username') . "' AND TABLE_NAME = UPPER(table_name); RETURN table_exists > 0; END;");
                break;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        switch (config('database.default')) {
            case 'pgsql':
                DB::statement('DROP FUNCTION IF EXISTS check_table_name();');
                break;
            case 'mysql':
            case 'oracle':
                DB::statement('DROP FUNCTION IF EXISTS check_table_exists;');
                break;
        }
    }
};
