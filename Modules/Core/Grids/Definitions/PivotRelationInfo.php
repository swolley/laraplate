<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

class PivotRelationInfo extends RelationInfo
{
    private string $pivotTable;

    private string $pivotOwnerKey;

    private string $pivotForeignKey;

    /**
     * {@inheritDoc}
     *
     * @param  string  $pivotTable  pivot table name for many-to-many relations
     * @param  string  $pivotOwnerKey  pivot owner key
     * @param  string  $pivotForeignKey  pivot foreign key
     */
    final public function __construct(string $type, string $name, string $model, string $table, string $foreignKey, string $ownerKey, string $pivotTable, string $pivotOwnerKey, string $pivotForeignKey)
    {
        parent::__construct($type, $name, $model, $table, $foreignKey, $ownerKey);
        $this->pivotTable = $pivotTable;
        $this->pivotOwnerKey = $pivotOwnerKey;
        $this->pivotForeignKey = $pivotForeignKey;
    }

    /**
     * creates new instance of PivotRelationInfo from simpler RelationInfo object
     *
     * @param  RelationInfo  $relationInfo  simpler relation info data
     * @param  string  $pivotTable  pivot table name for many-to-many relations
     * @param  string  $pivotOwnerKey  pivot owner key
     * @param  string  $pivotForeignKey  pivot foreign key
     */
    public static function fromRelationInfo(RelationInfo $relationInfo, string $pivotTable, string $pivotOwnerKey, string $pivotForeignKey): static
    {
        return new static(
            $relationInfo->getType(),
            $relationInfo->getName(),
            $relationInfo->getModel(),
            $relationInfo->getTable(),
            $relationInfo->getForeignKey(),
            $relationInfo->getOwnerKey(),
            $pivotTable,
            $pivotOwnerKey,
            $pivotForeignKey,
        );
    }

    /**
     * gets pivot table name
     */
    public function getPivotTable(): string
    {
        return $this->pivotTable;
    }

    /**
     * gets pivot owner key
     */
    public function getPivotOwnerKey(): string
    {
        return $this->pivotOwnerKey;
    }

    /**
     * gets pivot foreign key
     */
    public function getPivotForeignKey(): string
    {
        return $this->pivotForeignKey;
    }
}
