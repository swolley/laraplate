<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DevDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (modules(prioritySort: true) as $module) {
            $seeder_relative_path = config('modules.paths.generator.seeder.path');
            $seeders_path = module_path(
                $module,
                is_string($seeder_relative_path) ? $seeder_relative_path : 'database/seeders',
            );
            $seeders = glob("{$seeders_path}/Dev*.php") ?: [];

            foreach ($seeders as $seeder) {
                $basename = basename($seeder, '.php');
                $seeder_class = "Modules\\{$module}\\Database\\Seeders\\{$basename}";

                if (! class_exists($seeder_class)) {
                    continue;
                }

                $this->call($seeder_class);
            }
        }
    }
}
