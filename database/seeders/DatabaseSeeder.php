<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
	/**
	 * Seed the application's database.
	 */
	public function run(): void
	{
		$modules = modules();
		foreach ($modules as $module) {
			$module_dir = module_path($module, 'database/seeders');
			if (is_dir($module_dir)) {
				$seeders = glob($module_dir . '/*.php');
				foreach ($seeders as $seeder) {
					$this->call("Modules\\$module\\Database\\Seeders\\" . basename($seeder));
				}
			}
		}
	}
}
