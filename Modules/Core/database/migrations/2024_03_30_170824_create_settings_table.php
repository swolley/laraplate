<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Helpers\CommonMigrationColumns;
use Modules\Core\Inspector\Types\DoctrineTypeEnum;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $types = [
                DoctrineTypeEnum::BOOLEAN->value,
                DoctrineTypeEnum::INTEGER->value,
                DoctrineTypeEnum::FLOAT->value,
                DoctrineTypeEnum::STRING->value,
                DoctrineTypeEnum::JSON->value,
                DoctrineTypeEnum::DATE->value,
            ];

            $table->id();
            $table->string('name', 50)->nullable(false);
            $table->json('value')->nullable(false);
            $table->boolean('encrypted')->nullable(false);
            $table->json('choices')->nullable(true);
            $table->addColumn('enum', 'type', ['allowed' => $types, 'length' => 20]);
            $table->string('group_name', 50)->nullable(false);
            $table->string('description')->nullable(false);
            CommonMigrationColumns::timestamps($table, true, true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
