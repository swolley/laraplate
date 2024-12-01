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
        Schema::create('modifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('modifiable_id')->nullable();
            $table->string('modifiable_type')->nullable();
            $table->unsignedBigInteger('modifier_id')->nullable();
            $table->string('modifier_type')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_update')->default(true);
            $table->unsignedInteger('approvers_required')->default(1);
            $table->unsignedInteger('disapprovers_required')->default(1);
            $table->string('md5');
            $table->json('modifications');
            CommonMigrationColumns::timestamps($table, true);

            $table->index(['modifier_id', 'modifier_type'], 'modifications_modifierable_IDX');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('modifications');
    }
};
