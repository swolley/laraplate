<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DevDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (modules(prioritySort: true) as $module) {
            $seeders_path = module_path($module, config('modules.paths.generator.seeder.path'));
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
