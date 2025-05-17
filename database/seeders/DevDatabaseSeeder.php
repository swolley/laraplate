<?php

declare(strict_types=1);

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Cms\Database\Seeders\DevCmsDatabaseSeeder;
use Modules\Core\Database\Seeders\DevCoreDatabaseSeeder;

final class DevDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DevCoreDatabaseSeeder::class,
            DevCmsDatabaseSeeder::class,
        ]);
    }
}
