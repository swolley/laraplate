<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Helpers\CommonMigrationColumns;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->nullable(false)->constrained('entities', 'id', 'presets_entity_id_FK')->cascadeOnDelete();
            $table->string('name')->nullable(false);
            $table->boolean('is_active')->default(true)->nullable(false);
            $table->foreignId('template_id')->nullable(true)->constrained('templates', 'id', 'presets_template_id_FK')->cascadeOnDelete();
            CommonMigrationColumns::timestamps($table, true, true);

            $table->unique(['entity_id', 'name', 'deleted_at'], 'presets_UN');
            $table->unique(['entity_id', 'id'], 'presets_ids_UN');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presets');
    }
};
