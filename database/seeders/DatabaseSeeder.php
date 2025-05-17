<?php

declare(strict_types=1);

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Cms\Database\Seeders\CmsDatabaseSeeder;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreDatabaseSeeder::class,
            CmsDatabaseSeeder::class,
        ]);
    }
}
