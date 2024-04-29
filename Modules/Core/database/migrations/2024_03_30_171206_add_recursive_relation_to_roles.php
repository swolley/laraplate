<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $groupsTable = config('permission.table_names.roles');

        Schema::table($groupsTable, function (Blueprint $table) use ($groupsTable): void {
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id', 'parent_role_FK')->references('id')->on($groupsTable)->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $groupsTable = config('permission.table_names.roles');

        Schema::table($groupsTable, function (Blueprint $table): void {
            $table->dropForeign('parent_role_FK');
            $table->dropColumn('parent_id');
        });
    }
};
