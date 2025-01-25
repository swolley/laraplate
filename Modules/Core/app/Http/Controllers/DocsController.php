<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use ArrayAccess;
use Illuminate\Support\Str;
use UnexpectedValueException;
use Illuminate\Contracts\View\View;
use Nwidart\Modules\Facades\Module;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request as RequestFacade;
use Wotz\SwaggerUi\Http\Controllers\OpenApiJsonController;

class DocsController extends OpenApiJsonController
{
    public function mergeDocs(string $version = 'v1')
    {
        return response()->json($this->getJson($version));
    }

    public function welcome(): View
    {
        $all_modules = modules(true, false, false);
        $all_models = models(false);
        $all_controllers = controllers(false);

        $grouped = [];

        foreach ($all_modules as $module) {
            if (!array_key_exists($module, $grouped)) {
                $grouped[$module] = ['models' => [], 'controllers' => [], 'routes' => [], 'authors' => []];
            }

            foreach ($all_models as $i => $model) {
                if (Str::startsWith($model, $module) || Str::startsWith($model, "Modules\\$module")) {
                    $grouped[$module]['models'][] = $model;
                    unset($all_models[$i]);
                }
            }

            foreach ($all_controllers as $i => $controller) {
                if (Str::startsWith($controller, $module) || Str::startsWith($controller, "Modules\\$module")) {
                    $grouped[$module]['controllers'][] = $controller;
                    unset($all_controllers[$i]);
                }
            }
            $composer = json_decode(file_get_contents($module === 'App' ? base_path('composer.json') : module_path($module, 'composer.json')), true);

            foreach ($composer['authors'] ?? [] as $author) {
                $grouped[$module]['authors'][] = ['name' => is_string($author) ? $author : $author['name'], 'email' => !is_string($author) && isset($author['email']) ? $author['email'] : null];
            }
            $grouped[$module]['description'] = $composer['description'] ?? null;
            $grouped[$module]['version'] = $composer['version'] ?? null;
            sort($grouped[$module]['models']);
            sort($grouped[$module]['controllers']);
            $grouped[$module]['isEnabled'] = $module === 'App' ? true : Module::isEnabled($module);

            if ($module === 'App') {
                $grouped[$module]['version'] = version();
            } else {
                $version = json_decode(file_get_contents(Module::getModulePath($module) . 'composer.json'))->version ?? null;

                if ($version) {
                    $grouped[$module]['version'] = $version;
                }
            }
        }

        return view('core::welcome', [
            'grouped_modules' => $grouped,
            'translations' => translations()
        ]);
    }

    public function phpinfo(): View
    {
        return view('core::phpinfo');
    }

    /**
     * @throws UnexpectedValueException if no documentation is found
     * @return ArrayAccess|array
     *
     *
     * @psalm-return \ArrayAccess|array{paths: mixed,...}
     */
    protected function getJson(string $version): array
    {
        /** @var array $main_json */
        $main_json = Cache::tags([config('APP_NAME')])->remember(RequestFacade::route()->getName() . $version, config('cache.duration'), function () use ($version) {
            $assets = resource_path('swagger') . DIRECTORY_SEPARATOR;
            $files = glob($assets . '*-swagger.json');
            $modules = modules(true, false, true);

            $additionalPaths = [];
            $main_json = [];

            foreach ($files as $file) {
                $short_name = str_replace($assets, '', $file);

                /** @var array{paths: mixed,...} $json */
                $json = json_decode(file_get_contents($file), true);
                $json['paths'] = array_filter($json['paths'], function ($k) use ($version) {
                    return Str::contains($k, $version) || !Str::contains($k, '/api/');
                }, ARRAY_FILTER_USE_KEY);

                if (Str::startsWith($short_name, 'App')) {
                    $main_json = $json;
                } elseif (in_array(str_replace([$assets, '-swagger.json'], '', $file), $modules, true)) {
                    $additionalPaths = array_merge($additionalPaths, array_filter($json['paths'], fn(string $k) => Str::contains($k, $version) || !Str::contains($k, '/api/'), ARRAY_FILTER_USE_KEY));
                }
            }

            if (!empty($additionalPaths)) {
                $main_json['paths'] = array_merge($main_json['paths'], $additionalPaths);
            }

            return $main_json;
        });

        return $main_json;
    }
}
