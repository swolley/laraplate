<?php

namespace Modules\Core\Overrides;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
	protected string $name;

	protected string $nameLower;

	protected function registerConfig(): void
	{
		$relativeConfigPath = config('modules.paths.generator.config.path');
		$configPath = module_path($this->name, $relativeConfigPath);

		if (is_dir($configPath)) {
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

			foreach ($iterator as $file) {
				if ($file->isFile() && $file->getExtension() === 'php') {
					$relativePath = str_replace($configPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
					$configKey = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);
					$key = ($relativePath === 'config.php') ? $this->nameLower : $configKey;

					$this->publishes([$file->getPathname() => config_path($relativePath)], $configPath);
					$this->mergeConfigFrom($file->getPathname(), $key);
				}
			}
		}
	}

	protected function mergeConfigFrom($path, $key)
	{
		if (! ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached())) {
			$config = $this->app->make('config');

			$config->set($key, array_merge(
				$config->get($key, []),
				require $path
			));
		}
	}
}
