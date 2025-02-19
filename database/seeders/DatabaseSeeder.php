<?php

namespace Database\Seeders;

use Modules\Core\Overrides\Seeder;

class DatabaseSeeder extends Seeder
{
	/**
	 * Seed the application's database.
	 */
	public function run(): void
	{
		// $modules = modules(prioritySort: true);
		// foreach ($modules as $module) {
		// 	$module_dir = module_path($module, 'database/seeders');
		// 	if (is_dir($module_dir)) {
		// 		$seeders = glob($module_dir . '/*.php');
		// 		foreach ($seeders as $seeder) {
		// 			$seeder_class = "Modules\\$module\\Database\\Seeders\\" . basename($seeder, '.php');
		// 			$this->call($seeder_class);
		// 		}
		// 	}
		// }
	}
}
