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
            $table->string('slug')->nullable(false);
            CommonMigrationColumns::timestamps($table, true, true, true, true);

            $table->foreign(['entity_id', 'preset_id'], 'contents_preset_FK')
                ->references(['entity_id', 'id'])
                ->on('presets')
                ->cascadeOnDelete();
            $table->unique(['id', 'entity_id'], 'content_entity_UN');
        });

        Schema::create('categorizables', function (Blueprint $table) {
            // $table->id();
            $table->unsignedBigInteger('content_id')->nullable(false);
            $table->unsignedBigInteger('entity_id')->nullable(false);
            $table->unsignedBigInteger('category_id')->nullable(false);
            CommonMigrationColumns::timestamps($table);

            $table->primary(['content_id', 'category_id', 'entity_id']);
            $table->foreign(['content_id', 'entity_id'], 'categorizables_content_FK')
                ->references(['id', 'entity_id'])
                ->on('contents')
                ->cascadeOnDelete();
            $table->foreign(['category_id', 'entity_id'], 'categorizables_category_FK')
                ->references(['id', 'entity_id'])
                ->on('categories')
                ->cascadeOnDelete();
        });

        Schema::create('authorables', function (Blueprint $table) {
            // $table->id();
            $table->foreignId('content_id')->nullable(false)->constrained('contents', 'id', 'authorables_content_id_FK')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable(false)->constrained('authors', 'id', 'authorables_author_id_FK')->cascadeOnDelete();
            CommonMigrationColumns::timestamps($table);

            $table->primary(['content_id', 'author_id']);
        });

        Schema::create('relatables', function (Blueprint $table) {
            // $table->id();
            $table->foreignId('content_id')->nullable(false)->constrained('contents', 'id', 'relatables_content_id_FK')->cascadeOnDelete();
            $table->foreignId('related_content_id')->nullable(false)->constrained('contents', 'id', 'relatables_related_content_id_FK')->cascadeOnDelete();
            CommonMigrationColumns::timestamps($table);

            $table->primary(['content_id', 'related_content_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
        Schema::dropIfExists('categorizables');
        Schema::dropIfExists('authorables');
        Schema::dropIfExists('relatables');
        Schema::dropIfExists('locatables');
    }
};
