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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->nullable(false)->constrained('entities', 'id', 'categories_entity_id_FK')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories', 'id', 'categories_parent_id_FK')->cascadeOnDelete();
            $table->string('name')->nullable(false);
            $table->string('slug')->nullable(false);
            $table->text('description')->nullable(true);
            $table->integer('order')->default(0)->nullable(false);
            $table->integer('persistence')->default(99999)->nullable(false);
            $table->string('logo')->nullable(true);
            $table->string('logo_full')->nullable(true);
            $table->boolean('is_active')->default(true)->nullable(false);
            $table->integer('order_column')->nullable();
            CommonMigrationColumns::timestamps($table, true, true, true);

            $table->unique(['entity_id', 'name', 'deleted_at'], 'categories_UN');
            $table->unique(['entity_id', 'slug', 'deleted_at'], 'categories_slug_UN');
            $table->unique(['id', 'entity_id'], 'category_entity_UN');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
