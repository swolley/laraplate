<?php

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
        Schema::create('newspapers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->text('description')->nullable(true);
            $table->string('primary_color')->nullable(false);
            $table->string('secondary_color')->nullable(false);
            $table->string('logo')->nullable(false);
            $table->string('logo_full')->nullable(false);
            $table->string('domain')->nullable(false);
            $table->boolean('is_active')->default(true)->nullable(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newspapers');
    }
};
