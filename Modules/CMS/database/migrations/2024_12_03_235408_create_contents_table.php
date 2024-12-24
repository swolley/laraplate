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
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->nullable(false)->constrained('entities', 'id', 'contents_entity_id_FK')->cascadeOnDelete();
            $table->unsignedBigInteger('preset_id')->nullable(false);
            $table->integer('order_column')->nullable();
            $table->json('components')->nullable(false);
            CommonMigrationColumns::timestamps($table, true, true, true, true);

            $table->foreign(['preset_id', 'entity_id'], 'contents_preset_FK')->references(['id', 'entity_id'])->on('presets')->cascadeOnDelete();
        });

        Schema::create('collectables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->nullable(false)->constrained('contents', 'id', 'collectables_content_id_FK')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable(false)->constrained('categories', 'id', 'collectables_category_id_FK')->cascadeOnDelete();
            CommonMigrationColumns::timestamps($table, true);

            $table->unique(['content_id', 'category_id'], 'collectables_UN');
        });

        Schema::create('authorables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->nullable(false)->constrained('contents', 'id', 'authorables_content_id_FK')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable(false)->constrained('authors', 'id', 'authorables_author_id_FK')->cascadeOnDelete();
            CommonMigrationColumns::timestamps($table, true);

            $table->unique(['content_id', 'author_id'], 'authorables_UN');
        });

        Schema::create('relatables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->nullable(false)->constrained('contents', 'id', 'relatables_content_id_FK')->cascadeOnDelete();
            $table->foreignId('related_content_id')->nullable(false)->constrained('contents', 'id', 'relatables_related_content_id_FK')->cascadeOnDelete();
            CommonMigrationColumns::timestamps($table, true);

            $table->unique(['content_id', 'related_content_id'], 'relatables_UN');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
        Schema::dropIfExists('collectables');
        Schema::dropIfExists('authorables');
    }
};
