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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            // Newspaper ID is nullable for global templates
            $table->foreignId('newspaper_id')->nullable(true)->constrained('newspapers')->cascadeOnDelete();
            $table->string('name')->nullable(false);
            $table->longText('content')->nullable(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'newspaper_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
