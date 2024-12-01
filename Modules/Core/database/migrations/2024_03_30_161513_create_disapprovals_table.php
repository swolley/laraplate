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
        Schema::create('disapprovals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('modification_id');
            $table->unsignedBigInteger('disapprover_id');
            $table->string('disapprover_type');
            $table->text('reason')->nullable();
            CommonMigrationColumns::timestamps($table, true);

            $table->foreign(['modification_id'])->references('id')->on('modifications')->cascadeOnDelete();
            $table->index(['disapprover_id', 'disapprover_type'], 'disapprovals_disapproverable_IDX');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('disapprovals');
    }
};
