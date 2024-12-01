<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Nwidart\Modules\Module;
use Illuminate\Container\Container;
use Nwidart\Modules\Activators\FileActivator;
use Nwidart\Modules\Contracts\ActivatorInterface;

class ModuleMultiActivator implements ActivatorInterface
{
    private ActivatorInterface $activator;

    public function __construct(Container $app)
    {
        $this->activator = ModuleDatabaseActivator::checkSettingTable()
            ? new ModuleDatabaseActivator($app)
            : new FileActivator($app);
    }

    public function enable(Module $module): void
    {
        $this->activator->enable($module);
    }

    public function disable(Module $module): void
    {
        $this->activator->disable($module);
    }

    public function hasStatus(Module $module, bool $status): bool
    {
        return $this->activator->hasStatus($module, $status);
    }

    public function setActive(Module $module, bool $active): void
    {
        $this->activator->setActive($module, $active);
    }

    public function setActiveByName(string $name, bool $active): void
    {
        $this->activator->setActiveByName($name, $active);
    }

    public function delete(Module $module): void
    {
        $this->activator->delete($module);
    }

    public function reset(): void
    {
        $this->activator->reset();
    }
}
