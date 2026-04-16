<?php

declare(strict_types=1);

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class DevDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (modules(prioritySort: true) as $module) {
            $seeders_path = module_path($module, config('modules.paths.generator.seeder.path'));
            $seeders = glob($seeders_path . '/Dev*.php');

            foreach ($seeders as $seeder) {
                $seeder_class = 'Modules\\' . $module . '\\Database\\Seeders\\' . Str::of($seeder)->replace('.php', '')->classBasename();

                if (! class_exists($seeder_class)) {
                    continue;
                }

                $this->call($seeder_class);
            }
        }

        // $this->call([
        //     DevCoreDatabaseSeeder::class,
        //     DevCmsDatabaseSeeder::class,
        //     DevAIDatabaseSeeder::class,
        // ]);
    }
}
