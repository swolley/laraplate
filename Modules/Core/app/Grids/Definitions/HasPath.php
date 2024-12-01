<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

trait HasPath
{
    protected string $name;

    protected string $path = '';

    /**
     * gets the object name property
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * gets the object path property
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * gets the object full name (path + name)
     */
    public function getFullName(): string
    {
        return (strlen($this->getPath()) ? ($this->getPath() . '.') : '') . $this->getName();
    }

    /**
     * splits full path into a [path, name] tuuple
     *
     *
     * @return string[]
     *
     * @psalm-return list{string, string}
     */
    private static function splitPath(string $fullpath): array
    {
        $fullpath = explode('.', $fullpath);
        $fullpath = array_map('lcfirst', $fullpath);
        $name = array_pop($fullpath);
        $path = implode('.', $fullpath);

        return [$path, $name];
    }
}
