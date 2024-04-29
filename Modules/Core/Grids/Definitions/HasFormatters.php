<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

use Illuminate\Support\Collection;
use Modules\Core\Grids\Components\Field;

trait HasFormatters
{
    /** @var null|callable(mixed, Model) */
    private $getFormatter = null;

    /** @var null|callable(mixed, Model) */
    private $setFormatter = null;

    /**
     * returns if read formatter is set
     */
    public function hasReadFormatter(): bool
    {
        return $this->getFormatter !== null;
    }

    /**
     * gets read formatter if set
     *
     *
     * @psalm-return callable(mixed, \Illuminate\Database\Eloquent\Model)|null
     */
    public function getReadFormatter(): ?callable
    {
        return $this->getFormatter;
    }

    /**
     * sets read formatter if set
     */
    private function setReadFormatter(?callable $callback = null): void
    {
        $this->getFormatter = $callback;
    }

    /**
     * returns if read formatter is set
     */
    public function hasWriteFormatter(): bool
    {
        return $this->setFormatter !== null;
    }

    /**
     * gets write formatter if set
     *
     *
     * @psalm-return callable(mixed, array)|null
     */
    public function getWriteFormatter(): ?callable
    {
        return $this->setFormatter;
    }

    /**
     * sets write formatter if set
     */
    private function setWriteFormatter(?callable $callback = null): void
    {
        $this->setFormatter = $callback;
    }

    /**
     * alias for write formatter getter that returns static for pipes
     *
     * @param callable|null read formatter callback
     * @return static
     */
    public function getFormatter(?callable $callback)
    {
        $this->setReadFormatter($callback);

        return $this;
    }

    /**
     * alias for write formatter getter that returns static for pipes
     *
     * @param callable|null write formatter callback
     * @return static
     */
    public function setFormatter(?callable $callback)
    {
        $this->setWriteFormatter($callback);

        return $this;
    }

    /**
     * apply massively write formatter
     *
     * @param  Collection<string, Field>  $fields  fields to format
     * @param  array  $values  to be formatted
     */
    public static function applySetFormatter(Collection $fields, array $data): array
    {
        foreach ($fields as $field) {
            $field_name = $field->getFullName();
            $formatter = $field->getWriteFormatter();
            if ($formatter && array_key_exists($field_name, $data)) {
                $data[$field_name] = $formatter($data[$field_name], $data);
            }
        }

        return $data;
    }
}
