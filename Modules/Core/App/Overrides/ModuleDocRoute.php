<?php

declare(strict_types=1);

namespace Modules\Core\App\Overrides;

use ReflectionClass;
use Illuminate\Routing\Route as LaravelRoute;
use Mtrajano\LaravelSwagger\DataObjects\Route;

class ModuleDocRoute extends Route
{
    private LaravelRoute $reflectedRoute;

    public function __construct(LaravelRoute $route)
    {
        parent::__construct($route);
        $class = new ReflectionClass($this);
        $parent = $class->getParentClass();
        $property = $parent->getProperty('route');

        /** @psalm-suppress UnusedMethodCall */
        $property->setAccessible(true);
        $this->reflectedRoute = $property->getValue($this);
    }

    public function name(): ?string
    {
        return $this->reflectedRoute->action['as'];
    }

    public function group()
    {
        $exploded = explode('.', $this->name());

        return array_shift($exploded);
    }
}
