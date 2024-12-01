<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('entity')->nullable(false)->index();
            $table->foreignId('newspaper_id')->nullable(false)->constrained('newspapers')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('folders')->cascadeOnDelete();
            $table->string('name')->nullable(false);
            $table->string('slug')->nullable(false);
            $table->text('description')->nullable(true);
            $table->foreignId('model_type_id')->nullable(false)->constrained('model_types')->cascadeOnDelete();
            $table->integer('order')->default(0)->nullable(false);
            $table->integer('persistence')->default(99999)->nullable(false);
            $table->string('logo')->nullable(true);
            $table->string('logo_full')->nullable(true);
            $table->boolean('is_active')->default(true)->nullable(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity', 'newspaper_id', 'parent_id', 'name', 'deleted_at']);
        });

        switch (config('database.default')) {
            case 'pgsql':
                DB::statement('CREATE TRIGGER validate_table_name_on_folders BEFORE INSERT OR UPDATE ON folders FOR EACH ROW EXECUTE FUNCTION check_table_name();');
                break;
            case 'mysql':
                DB::statement('CREATE TRIGGER validate_table_name_on_folders BEFORE INSERT ON folders FOR EACH ROW EXECUTE FUNCTION check_table_exists(NEW.entity);');
                break;
            case 'oracle':
                DB::statement('CREATE OR REPLACE TRIGGER validate_table_name_on_folders BEFORE INSERT ON folders FOR EACH ROW EXECUTE FUNCTION check_table_exists(NEW.entity);');
                break;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        switch (config('database.default')) {
            case 'pgsql':
                DB::statement('DROP TRIGGER IF EXISTS validate_table_name_on_folders ON folders;');
                break;
            case 'mysql':
            case 'oracle':
                DB::statement('DROP TRIGGER IF EXISTS validate_table_name_on_folders;');
                break;
        }

        Schema::dropIfExists('folders');
    }
};
