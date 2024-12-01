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
        Schema::create('model_types', function (Blueprint $table) {
            $table->id();
            // Newspaper ID is nullable for global model types
            $table->foreignId('newspaper_id')->nullable(true)->constrained('newspapers')->cascadeOnDelete();
            $table->string('entity')->nullable(false)->index();
            $table->string('name')->nullable(false);
            $table->json('config')->nullable(false);
            $table->boolean('is_active')->default(true)->nullable(false);
            $table->foreignId('template_id')->nullable(true)->constrained('templates')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity', 'newspaper_id', 'name', 'deleted_at']);
        });

        switch (config('database.default')) {
            case 'pgsql':
                DB::statement('CREATE TRIGGER validate_table_name_on_model_types BEFORE INSERT OR UPDATE ON model_types FOR EACH ROW EXECUTE FUNCTION check_table_name();');
                break;
            case 'mysql':
                DB::statement('CREATE TRIGGER validate_table_name_on_model_types BEFORE INSERT ON model_types FOR EACH ROW EXECUTE FUNCTION check_table_exists(NEW.entity);');
                break;
            case 'oracle':
                DB::statement('CREATE OR REPLACE TRIGGER validate_table_name_on_model_types BEFORE INSERT ON model_types FOR EACH ROW EXECUTE FUNCTION check_table_exists(NEW.entity);');
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
                DB::statement('DROP TRIGGER IF EXISTS validate_table_name_on_model_types ON model_types;');
                break;
            case 'mysql':
            case 'oracle':
                DB::statement('DROP TRIGGER IF EXISTS validate_table_name_on_model_types;');
                break;
        }
        Schema::dropIfExists('model_types');
    }
};
