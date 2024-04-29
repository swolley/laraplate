<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

class RelationInfo
{
    protected string $type;

    protected string $name;

    protected string $model;

    protected string $table;

    protected string $foreignKey;

    protected string $ownerKey;

    /**
     * @param  string  $type  relation type (method name)
     * @param  string  $name  relation name (from the parent point of view)
     * @param  string  $model  related entity class
     * @param  string  $table  database table
     * @param  string  $foreignKey  db foreign key column
     * @param  string  $ownerKey  db local key (usually primary key)
     */
    public function __construct(string $type, string $name, string $model, string $table, string $foreignKey, string $ownerKey)
    {
        $this->type = $type;
        $this->name = $name;
        $this->model = $model;
        $this->table = $table;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
    }

    /**
     * gets relation type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * gets relation name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * gets related model
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * gets relation foreign key
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * get relation owner key
     */
    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }

    /**
     * gets related table
     */
    public function getTable(): string
    {
        return $this->table;
    }
}
