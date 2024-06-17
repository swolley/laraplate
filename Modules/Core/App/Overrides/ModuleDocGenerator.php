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
        $module = Str::replace('Modules\\', '', $this->module);
        $all_module_routes = routes(false, $module);
        $module_routes = [];

        foreach ($all_module_routes as $route) {
            if (
                !isset($this->config['ignoredRoutes']) || !in_array($route->getName(), $this->config['ignoredRoutes'], true)
            ) {
                $module_routes[] = new ModuleDocRoute($route);
            }
        }

        return $module_routes;
    }

    protected function generatePath(): void
    {
        parent::generatePath();
        $uri = $this->route->uri();
        $operationId = $this->method . str_replace(['/', '{', '}'], ['-', '', ''], $uri);
        $group = Str::contains($uri, '/app/') ? 'App' : (Str::contains($uri, '/api/') ? 'Api' : 'Others');
        $path_method = &$this->docs['paths'][$this->route->uri()][$this->method];
        $path_method['operationId'] = $operationId;
        $path_method['tags'] = [$group];

        /*if (Str::contains($this->route->uri(), '/api/')) {
            $path_method['responses']['200']['content'] = [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ];
        } else*/
        if ($this->route->uri() === '/up') {
            $path_method['responses']['200']['content'] = [
                'text/html' => [],
            ];
        }
    }
}
