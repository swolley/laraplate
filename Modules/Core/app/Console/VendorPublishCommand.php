<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\VendorPublishCommand as BaseVendorPublishCommand;

class VendorPublishCommand extends BaseVendorPublishCommand
{
    private array $modules = [];

    public function __construct(Filesystem $files)
    {
        $this->description .= ' <comment>(⛭ Modules\Core)</comment>';

        parent::__construct($files);

        $modules = modules(false, true, false);

        foreach ($modules as $module) {
            $exploded_path = explode(DIRECTORY_SEPARATOR, $module);
            $module_name = array_pop($exploded_path);
            $migrations_subpath = config('modules.paths.generator.migration.path');
            $configs_subpath = config('modules.paths.generator.config.path');
            $this->modules[$module_name] = [
                'path' => $module,
                'migrations' => glob(module_path($module_name, $migrations_subpath . DIRECTORY_SEPARATOR . '*.php')),
                'config' => array_filter(glob(module_path($module_name, $configs_subpath . DIRECTORY_SEPARATOR . '*.php')), fn(string $c) => !Str::endsWith($c, 'config.php')),
            ];
        }
    }

    protected function publishFile($from, $to): void
    {
        $found = false;
        $file_name = basename($to);
        $migrations_subpath = config('modules.paths.generator.migration.path');
        $configs_subpath = config('modules.paths.generator.config.path');

        if (Str::contains($from, $migrations_subpath)) {
            $found = $this->moduleMigrationExists($file_name);
        } elseif (Str::endsWith($from, $configs_subpath . DIRECTORY_SEPARATOR . $file_name) || Str::endsWith($from, $configs_subpath . DIRECTORY_SEPARATOR . 'config.php')) {
            $found = $this->moduleConfigExists($file_name);

            if ($this->option('force') && $found) {
                /** @psalm-suppress UnresolvableInclude */
                $merged = array_merge(require $from, require $found);
                file_put_contents($to, $merged);
                $this->status($found, $to, 'file');

                return;
            }
        }

        if ($found) {
            $to = $found;
        }
        parent::publishFile($from, $to);
    }

    private function moduleMigrationExists(string $file): string|false
    {
        foreach ($this->modules as $data) {
            foreach ($data['migrations'] as $migration) {
                $only_description = preg_replace("/(?:.*)\d{4}_\d{2}_\d{2}_\d{6}/", '', $migration);

                if (Str::endsWith($file, $only_description)) {
                    return $migration;
                }
            }
        }

        return false;
    }

    private function moduleConfigExists(string $file): string|false
    {
        foreach ($this->modules as $data) {
            foreach ($data['config'] as $config) {
                $only_name = basename($config);

                if ($file === $only_name) {
                    return $config;
                }
            }
        }

        return false;
    }
}
