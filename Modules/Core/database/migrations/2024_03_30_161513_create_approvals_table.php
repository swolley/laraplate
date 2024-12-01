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
        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('modification_id');
            $table->unsignedBigInteger('approver_id');
            $table->string('approver_type');
            $table->text('reason')->nullable();
            CommonMigrationColumns::timestamps($table, true);

            $table->foreign(['modification_id'])->references('id')->on('modifications')->cascadeOnDelete();
            $table->index(['approver_id', 'approver_type'], 'approvals_approverable_IDX');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
