<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (modules(prioritySort: true) as $module) {
            $seeders_path = module_path($module, config('modules.paths.generator.seeder.path'));
            $seeders = glob("{$seeders_path}/*.php") ?: [];

            foreach ($seeders as $seeder) {
                $basename = basename($seeder, '.php');

                if (Str::startsWith($basename, 'Dev')) {
                    continue;
                }

                $seeder_class = "Modules\\{$module}\\Database\\Seeders\\{$basename}";

                if (! class_exists($seeder_class)) {
                    continue;
                }

                $this->call($seeder_class);
            }
        }
    }
}
