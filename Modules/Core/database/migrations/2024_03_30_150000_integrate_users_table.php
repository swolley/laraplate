<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Helpers\CommonMigrationColumns;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->unique('users_username_UN');
            $table->string('lang')->nullable(true);
            $table->datetime('last_login_at')->nullable(true);
            $table->uuid('license_id')->nullable(true);
            $table->foreign('license_id', 'FK_users_licenses')->references('id')->on('licenses')->nullOnDelete();
            CommonMigrationColumns::timestamps($table, false, true, true);
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('username');
            $table->dropColumn('lang');
            $table->dropColumn('last_login_at');
            CommonMigrationColumns::timestamps($table, true, true);
        });
    }
};
