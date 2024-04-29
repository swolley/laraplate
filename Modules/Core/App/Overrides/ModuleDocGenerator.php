<?php

declare(strict_types=1);

namespace Modules\Core\App\Overrides;

use Illuminate\Support\Str;
use Mtrajano\LaravelSwagger\Generator;

class ModuleDocGenerator extends Generator
{
    private string $module;

    public function __construct($config, string $module, $routeFilter = null)
    {
        parent::__construct($config, $routeFilter);
        $this->module = $module;
    }

    /**
     * @return ModuleDocRoute[]
     *
     * @psalm-return list{0?: ModuleDocRoute,...}
     */
    protected function getAppRoutes(): array
    {
        $all_routes = app('router')->getRoutes()->getRoutes();
        $module_routes = [];

        foreach ($all_routes as $route) {
            if (
                mb_strpos($route->getControllerClass(), $this->module) === 0
                && (!isset($this->config['ignoredRoutes']) || !in_array($route->getName(), $this->config['ignoredRoutes'], true))
            ) {
                $module_routes[] = new ModuleDocRoute($route);
            }
        }

        return $module_routes;
    }

    protected function generatePath(): void
    {
        parent::generatePath();
        $operationId = $this->method . '.' . $this->route->name();
        $group = $this->route->group();
        $module = Str::replace('Modules\\', '', $this->module);
        $path_method = &$this->docs['paths'][$this->route->uri()][$this->method];
        $path_method['operationId'] = $operationId;
        $path_method['tags'] = [$module . ($group && mb_strtolower($group) !== mb_strtolower($module) ? '/' . ucfirst($group) : '')];

        if (Str::contains($this->route->uri(), '/api/')) {
            $path_method['responses']['200']['content'] = [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
                'application/xml' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ];
        }
    }
}
