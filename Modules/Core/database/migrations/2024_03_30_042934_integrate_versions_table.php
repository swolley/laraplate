<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('versions', function (Blueprint $table): void {
            $table->string('connection_ref')->nullable()->after('id');
            $table->string('table_ref')->nullable()->after('id');

            $table->index(['versionable_id', 'versionable_type'], 'versions_versionable_IDX');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('versions', function (Blueprint $table): void {
            $table->dropColumn('connection_ref');
            $table->dropColumn('table_ref');

            $table->dropIndex('versions_versionable_IDX');
        });
    }
};
