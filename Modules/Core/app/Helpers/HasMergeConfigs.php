<?php

namespace Modules\Core\Helpers;

use Illuminate\Contracts\Foundation\CachesConfiguration;

trait HasMergeConfigs
{
	/**
	 * Register config.
	 */
	protected function registerConfig(): void
	{
		$this->publishes([module_path($this->moduleName, 'config/config.php') => config_path($this->moduleNameLower . '.php')], 'config');
		$this->mergeConfigFrom(module_path($this->moduleName, 'config/config.php'), $this->moduleNameLower);

		$sourcePath = module_path($this->moduleName, 'config');
		$files = glob($sourcePath . '/*.php');
		foreach ($files as $file) {
			$basename = basename($file, '.php');
			if ($basename === 'config') continue;
			$this->mergeConfigFrom($file, $basename);
		}
	}

	#[\Override]
	protected function mergeConfigFrom($path, $key)
	{
		if (! ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached())) {
			$config = $this->app->make('config');

			$config->set($key, array_merge(
				$config->get($key, []),
				require $path,
			));
		}
	}
}
