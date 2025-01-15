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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->string('slug')->nullable(false);
            $table->string('address')->nullable(true);
            $table->string('city')->nullable(true)->index('locations_city_IDX');
            $table->string('province')->nullable(true)->index('locations_province_IDX');
            $table->string('country')->nullable(false)->index('locations_country_IDX');
            $table->string('postcode')->nullable(true);
            $table->decimal('latitude', 10, 8)->nullable(true);
            $table->decimal('longitude', 11, 8)->nullable(true);
            CommonMigrationColumns::timestamps($table, true, true, true);

            $table->unique(['name', 'deleted_at'], 'locations_name_UN');
            $table->unique(['slug', 'deleted_at'], 'locations_slug_UN');
            $table->unique(['latitude', 'longitude', 'deleted_at'], 'locations_lat_long_UN');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
