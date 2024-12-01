<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Exception;
use BadMethodCallException;
use Illuminate\Support\Str;
use Nwidart\Modules\Module;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ModuleDatabaseActivator implements ActivatorInterface
{
    public const RECORD_NAME = 'backendModules';

    /**
     * @var class-string
     */
    public const MODEL_NAME = Setting::class;

    private \Illuminate\Cache\CacheManager $cache;

    private string $cacheKey;

    private int $cacheLifetime;

    private \Illuminate\Config\Repository $configs;

    private array $modulesStatuses;

    public function __construct(Container $app)
    {
        $this->cache = $app['cache'];
        // $this->files = $app['files'];
        $this->configs = $app['config'];
        // $this->statusesFile = $this->config('statuses-file');
        $this->cacheKey = $this->config('cache-key');
        $this->cacheLifetime = $this->config('cache-lifetime');
        $this->modulesStatuses = $this->getModulesStatuses();

        static::checkSettingTable();
    }

    public static function checkSettingTable(): bool
    {
        try {
            $model = static::MODEL_NAME;

            if (!class_exists($model)) {
                throw new BadMethodCallException('No Setting model found in the application');
            }

            if (!Schema::hasTable((new $model())->getTable())) {
                throw new BadMethodCallException('No settings table found in the database schema');
            }

            if (!Setting::where('name', static::RECORD_NAME)->exists()) {
                static::seedBackendModules();
                Log::info("Created Setting '{name}' config record", ['name' => static::RECORD_NAME]);
            }

            return true;
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     * @return string[]
     *
     * @psalm-return list<string>
     */
    public static function getAllModulesNames(): array
    {
        $modules = glob(config('modules.paths.modules') . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        $parsed_names = [];

        foreach ($modules as $path) {
            $parsed_names[] = Str::afterLast($path, DIRECTORY_SEPARATOR);
        }

        return $parsed_names;
    }

    public static function seedBackendModules(): Model
    {
        $model = static::MODEL_NAME;
        $found = $model::where('name', static::RECORD_NAME)->first();
        $all_modules = static::getAllModulesNames();

        if (!$found) {
            $found = $model::create([
                'value' => $all_modules,
                'choices' => $all_modules,
                'name' => static::RECORD_NAME,
                'type' => 'json',
                'group_name' => 'backend',
                'description' => 'backend modules',
            ]);
        } elseif ($found->choices != $all_modules) {
            $found->update(['choices' => $all_modules]);
        }

        return $found;
    }

    public function reset(): void
    {
        /** @psalm-suppress UndefinedClass */
        $this->getQuery()->update(['value' => static::getAllModulesNames()]);
        $this->flushCache();
    }

    public function enable(Module $module): void
    {
        $this->setActiveByName($module->getName(), true);
    }

    public function disable(Module $module): void
    {
        $this->setActiveByName($module->getName(), true);
    }

    public function hasStatus(Module $module, bool $status): bool
    {
        if (!in_array($module->getName(), $this->modulesStatuses, true)) {
            return $status === false;
        }

        return ($status && in_array($module->getName(), $this->modulesStatuses, true)) || (!$status && !in_array($module->getName(), $this->modulesStatuses, true));
    }

    public function setActive(Module $module, bool $active): void
    {
        $this->setActiveByName($module->getName(), $active);
    }

    public function setActiveByName(string $name, bool $active): void
    {
        if ($active && !in_array($name, $this->modulesStatuses, true)) {
            $this->modulesStatuses[] = $name;
            $this->getQuery()->update(['value' => $this->modulesStatuses]);
            $this->flushCache();
        } elseif (!$active && in_array($name, $this->modulesStatuses, true)) {
            $this->getQuery()->update(['value' => array_filter($this->modulesStatuses, fn($m) => $m !== $name)]);
            $this->flushCache();
        }
    }

    public function delete(Module $module): void
    {
        $this->setActiveByName($module->getName(), false);
    }

    private function getQuery(): Builder
    {
        // static::checkSettingTable();
        $model = static::MODEL_NAME;

        /** @var Builder $query */
        $query = $model::query();

        return $query->where('name', static::RECORD_NAME);
    }

    private function readSettings(): array
    {
        try {
            return $this->getQuery()->sole()->value;

            /** @psalm-suppress UndefinedClass */
        } catch (ModelNotFoundException $e) {
            return static::seedBackendModules()->value;
        } catch (BadMethodCallException $e) {
            return static::getAllModulesNames();
        }
    }

    private function getModulesStatuses(): array
    {
        if (!$this->configs->get('modules.cache.enabled')) {
            return $this->readSettings();
        }

        return $this->cache->store($this->configs->get('modules.cache.driver'))->remember($this->cacheKey, $this->cacheLifetime, fn() => $this->readSettings());
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return $this->configs->get('modules.activators.database.' . $key, $default);
    }

    private function flushCache(): void
    {
        $this->cache->store($this->configs->get('modules.cache.driver'))->forget($this->cacheKey);
    }
}
