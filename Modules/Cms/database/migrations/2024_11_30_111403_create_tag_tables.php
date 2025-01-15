<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Helpers\CommonMigrationColumns;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique('tags_name_UN');
            $table->string('slug')->unique('tags_slug_UN');
            $table->string('type')->nullable();
            $table->integer('order_column')->nullable();
            CommonMigrationColumns::timestamps($table);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained('tags', 'id', 'taggables_tag_id_FK')->cascadeOnDelete();
            $table->morphs('taggable');

            $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
