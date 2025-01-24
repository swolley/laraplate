<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use Mtrajano\LaravelSwagger\FormatterManager;
use Modules\Core\Overrides\ModuleDocGenerator;
use Symfony\Component\Console\Input\InputOption;
use Mtrajano\LaravelSwagger\LaravelSwaggerException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Mtrajano\LaravelSwagger\GenerateSwaggerDoc as BaseGenerateSwaggerDoc;

class SwaggerGenerateCommand extends BaseGenerateSwaggerDoc
{
    public function __construct()
    {
        $this->signature .= '
                {--m|module= : Filter to a specific Module}
        ';
        $this->description .= ' <comment>(⛭ Modules\Core)</comment>';

        parent::__construct();
    }

    protected function getOptions(): array
    {
        return [
            ['module', null, InputOption::VALUE_OPTIONAL, 'Filter to a specific Module', null],
        ];
    }

    /**
     * Execute the console command.
     *
     */
    public function handle(): int
    {
        $module_filter = $this->option('module');

        foreach (modules(true, false, false) as $module_name) {
            if (
                ($module_name !== 'App' && !class_exists(Module::class))
                || ($module_filter && $module_name !== $module_filter)
            ) continue;

            $this->moduleHandle($module_name);
        }

        return static::SUCCESS;
    }

    /**
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     * @throws LaravelSwaggerException
     */
    public function moduleHandle(string $moduleName): void
    {
        $filter = $this->option('filter') ?: null;

        /** @var null|string $file */
        $file = $this->option('output') ?: resource_path('swagger') . DIRECTORY_SEPARATOR . $moduleName . '-swagger.json';
        $config = config('laravel-swagger');

        if ($moduleName !== 'App') {
            $module_path = Module::getModulePath($moduleName);
            $module_json = json_decode(file_get_contents($module_path . DIRECTORY_SEPARATOR . 'module.json'), true);
            $config['title'] .= ' ' . $module_json['name'] . ' module';
            $config['description'] = $module_json['description'] . (!empty($module_json['keywords']) ? ' (' . implode(', ', $module_json['keywords']) . ')' : '');
            $composer_json = json_decode(file_get_contents($module_path . 'composer.json'));

            if (isset($composer_json->version)) {
                $config['version'] = $composer_json->version;
            }
        }

        $doc = (new ModuleDocGenerator($config, $moduleName !== 'App' ? config('modules.namespace') . '\\' . $moduleName : $moduleName, $filter))->generate();
        $doc['tags'] = [$moduleName];

        $formattedDoc = (new FormatterManager($doc))
            ->setFormat($this->option('format'))
            ->format();

        if ($file) {
            $folder = Str::beforeLast($file, DIRECTORY_SEPARATOR);
            if (!file_exists($folder)) mkdir($folder, recursive: true);
            file_put_contents($file, $formattedDoc);

            $this->verboseGeneration($doc);

        } else {
            $this->line($formattedDoc);
        }
    }

    private function verboseGeneration(array $doc): void
    {
        $this->info($doc['info']['title']);
        foreach ($doc['paths'] as $path => $methods) {
            $methods = array_map('strtoupper', array_keys($methods));
            $this->line(implode("|", $methods) . (count($methods) > 1 ? "\t" : "\t\t") . $path);
        }
    }
}

