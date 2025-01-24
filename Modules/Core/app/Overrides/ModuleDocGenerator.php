<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Support\Str;
use Illuminate\Routing\Route;
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
        $all_module_routes = routes(true, $module);
        $module_routes = [];

        foreach ($all_module_routes as $route) {
            if ($this->shouldIgnoreRoute($route)) {
                continue;
            }

            if (!$route->wheres) {
                $module_routes[] = new ModuleDocRoute($route);
                continue;
            }

            $parameter_values = [];

            foreach ($route->wheres as $key => $value) {
                if (!is_string($value) && !is_array($value)) {
                    continue;
                }

                $values = is_string($value) && Str::contains($value, '|') 
                    ? explode('|', $value) 
                    : (array)$value;

                if (!empty($values)) {
                    $parameter_values[$key] = $values;
                }
            }

            if (empty($parameter_values)) {
                $module_routes[] = new ModuleDocRoute($route);
                continue;
            }

            foreach ($this->generateCombinations($parameter_values) as $combination) {
                $new_route = clone $route;
                $new_uri = $new_route->uri;
                
                foreach ($combination as $param => $value) {
                    $new_uri = str_replace('{' . $param . '}', $value, $new_uri);
                    unset($new_route->wheres['pippo']);
                }
                
                $new_route->uri = $new_uri;
                $module_routes[] = new ModuleDocRoute($new_route);
            }
        }

        return $module_routes;
    }

    /**
     * @param Route $route
     * @return bool
     */
    private function shouldIgnoreRoute(Route $route): bool
    {
        if (!isset($this->config['ignoredRoutes'])) {
            return false;
        }

        $route_name = $route->getName();
        if (empty($route_name) || Str::startsWith($route->uri(), ['_', '/_'])) {
            return true;
        }

        foreach ($this->config['ignoredRoutes'] as $pattern) {
            if (!Str::contains($pattern, '*')) {
                if ($route_name === $pattern) {
                    return true;
                }
                continue;
            }

            $regex = str_replace('\*', '.*', preg_quote($pattern, '/'));
            if (preg_match('/^' . $regex . '$/', $route_name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates all possible combinations of parameter values
     * 
     * @param array $parameters Array of parameters and their possible values
     * @return array Array of all possible combinations
     */
    private function generateCombinations(array $parameters): array
    {
        if (empty($parameters)) {
            return [[]];
        }

        $param = key($parameters);
        $values = array_shift($parameters);

        $combinations = [];
        $sub_combinations = $this->generateCombinations($parameters);

        foreach ($values as $value) {
            foreach ($sub_combinations as $sub_combination) {
                $combinations[] = array_merge([$param => $value], $sub_combination);
            }
        }

        return $combinations;
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

        if ($this->route->uri() === '/up') {
            $path_method['responses']['200']['content'] = [
                'text/html' => [],
            ];
        }
    }
}
