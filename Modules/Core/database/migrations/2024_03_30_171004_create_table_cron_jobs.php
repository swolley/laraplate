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
     */
    public function up(): void
    {
        Schema::create('cron_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable(false);
            $table->string('command')->nullable(false);
            $table->json('parameters')->nullable(false);
            $table->string('schedule')->nullable(false);
            $table->boolean('is_active')->nullable(false);
            $table->string('description')->nullable();
            CommonMigrationColumns::timestamps($table, true, true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
