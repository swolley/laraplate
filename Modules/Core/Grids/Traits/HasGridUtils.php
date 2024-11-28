<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Traits;

use ReflectionClass;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Components\Grid;
use Modules\Core\Locking\Traits\HasLocks;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Grids\Definitions\RelationInfo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Core\Grids\Definitions\PivotRelationInfo;

trait HasGridUtils
{
    /**
     * Get eloquent relationships
     *
     * @return array<string, RelationInfo>
     */
    public static function getRelationships(): array
    {
        $instance = new static;
        // Get public methods declared without parameters and non inherited
        $class = get_class($instance);
        $allMethods = (new ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC);
        $methods = array_filter(
            $allMethods,
            fn($method) => $method->isUserDefined() &&
                $method->hasReturnType() && (
                    $method->getReturnType()->__toString() === Relation::class ||
                    is_subclass_of($method->getReturnType()->__toString(), Relation::class)
                )
        );

        DB::beginTransaction();

        /** @var array<string, RelationInfo> $relations */
        $relations = [];
        foreach ($methods as $method) {
            $methodName = $method->getName();
            $relation = static::getRelationData($instance, $methodName);
            if ($relation) {
                $relations[$methodName] = $relation;
            }
        }

        DB::rollBack();

        return $relations;
    }

    public static function getRelationship(string $relation): RelationInfo|false
    {
        $instance = new static;
        // Get public methods declared without parameters and non inherited
        $class = get_class($instance);
        $reflection = new ReflectionClass($class);
        if (!$reflection->hasMethod($relation)) {
            return false;
        }

        $method = $reflection->getMethod($relation);
        if (!$method->isPublic()) {
            return false;
        }

        return static::getRelationData($instance, $method->getName());
    }

    public static function getInverseRelationship(string $relation): RelationInfo|false
    {
        $relationship = static::getRelationship($relation);
        if (!$relationship) {
            return false;
        }

        $model = $relationship->getModel();
        if (!class_uses_trait($model, HasGridUtils::class)) {
            return false;
        }

        $relationships = array_filter($model::getRelationships(), function ($r) {
            $model = $r->getModel();
            if (static::class instanceof $model) {
                $r->setModel(static::class);
            }

            return $r->getModel() === static::class || static::class instanceof $model;
        });
        if (empty($relationships)) {
            return false;
        }
        if (count($relationships) > 1) {
            throw new \Exception('Too many relationships found for the same model');
        }

        return head($relationships);
    }

    public static function getRelationshipByLocalForeignKey(string $foreignKey): ?RelationInfo
    {
        foreach (static::getRelationships() as $relation) {
            if (str_starts_with($relation->getType(), 'belong')) {
                return $relation;
            }
        }

        return null;
    }

    /** @return RelationInfo[]|false */
    public static function getRelationshipDeeply(string $relation): array|false
    {
        $splitted = explode('.', $relation);
        $found = [];
        $current = static::class;
        if ($splitted[0] === lcfirst((new ReflectionClass($current))->getShortName())) {
            array_shift($splitted);
        }
        foreach ($splitted as $relation) {
            $subrelation = $current::getRelationship($relation);
            if (!$subrelation) {
                break;
            }

            $found[] = $subrelation;
            $current = $subrelation->getModel();
        }

        return count($found) === count($splitted) ? $found : false;
    }

    /** @return RelationInfo[]|false */
    public static function getInverseRelationshipDeeply(string $relation): array|false
    {
        if (count(explode('.', $relation)) <= 2) {
            $subrelation = static::getInverseRelationship($relation);

            return $subrelation ? [$subrelation] : false;
        }

        $found = static::getRelationshipDeeply($relation);
        if ($found === false || empty($found)) {
            return false;
        }

        $inversed = [];
        $i = count($found);
        $current = $found[$i - 1];
        $prev = $found[$i - 2]->getModel();
        while ($prev) {
            $subrelation = $prev::getInverseRelationship($current->getName());
            if (!$subrelation) {
                break;
            }

            $inversed[] = $subrelation;
            $i--;
            $prev = $i > 1 ? $found[$i - 2]->getModel() : null;
        }

        return count($inversed) === count($found) ? $inversed : false;
    }

    /**
     * @param string $relation	relation method / table name / model class name
     */
    public static function hasRelation(string $relation): bool
    {
        return static::getRelationship($relation) !== false;
    }

    /**
     * @param string $relation	relation method / table name / model class name
     */
    public static function hasRelationDeeply(string $relation): bool
    {
        return static::getRelationshipDeeply($relation) !== false;
    }

    /**
     * @param string $relation	relation method / table name / model class name
     */
    public static function isDeepRelation(string $relation): bool
    {
        $deep = static::getRelationshipDeeply($relation);

        return $deep !== false && count($deep) > 1;
    }

    /**
     * Undocumented function
     */
    private static function getRelationData(Model $instance, string $methodName): RelationInfo|false
    {
        try {
            $methodReturn = $instance->$methodName();
            if (!$methodReturn instanceof Relation) {
                return false;
            }
            $ref = new ReflectionClass($methodReturn);

            //chiave partenza dell'entità in oggetto
            $foreign = null;
            if ($ref->hasProperty('foreignKey')) {
                $p = $ref->getProperty('foreignKey');
                $p->setAccessible(true);
                $foreign = $p->getValue($methodReturn);
            } elseif ($ref->hasProperty('parentKey')) {
                $p = $ref->getProperty('parentKey');
                $p->setAccessible(true);
                $foreign = $p->getValue($methodReturn);
            } elseif ($ref->hasProperty('secondLocalKey')) {
                $p = $ref->getProperty('secondLocalKey');
                $p->setAccessible(true);
                $foreign = $p->getValue($methodReturn);
            }

            if ($foreign) {
                $exploded = explode('.', $foreign);
                $foreign = array_pop($exploded);
            }

            //tabella di cross join
            $pivot_table = null;
            $pivot_foreign = null;
            $pivot_owner = null;
            if ($ref->hasProperty('foreignPivotKey')) {
                $t = $ref->getProperty('table');
                $t->setAccessible(true);
                $pivot_table = $t->getValue($methodReturn);

                $p = $ref->getProperty('foreignPivotKey');
                $p->setAccessible(true);
                $pivot_foreign = $p->getValue($methodReturn);

                $p = $ref->getProperty('relatedPivotKey');
                $p->setAccessible(true);
                $pivot_owner = $p->getValue($methodReturn);
            } elseif ($ref->hasProperty('secondKey')) {
                $t = $ref->getProperty('throughParent');
                $t->setAccessible(true);
                $pivot_table = $t->getValue($methodReturn)->getTable();

                $p = $ref->getProperty('secondKey');
                $p->setAccessible(true);
                $pivot_foreign = $p->getValue($methodReturn);

                $p = $ref->getProperty('firstKey');
                $p->setAccessible(true);
                $pivot_owner = $p->getValue($methodReturn);
            }

            //chiave destinazione dell'entità relazionata
            $owner = null;
            if ($ref->hasProperty('relatedKey')) {
                $p = $ref->getProperty('relatedKey');
                $p->setAccessible(true);
                $owner = $p->getValue($methodReturn);
            } elseif ($ref->hasProperty('ownerKey')) {
                $p = $ref->getProperty('ownerKey');
                $p->setAccessible(true);
                $owner = $p->getValue($methodReturn);
            } elseif ($ref->hasProperty('localKey')) {
                $p = $ref->getProperty('localKey');
                $p->setAccessible(true);
                $owner = $p->getValue($methodReturn);
            }

            $type = (new ReflectionClass($methodReturn))->getShortName();
            $model = get_class($methodReturn->getRelated());
            $relation = new RelationInfo($type, $methodName, $model, (new $model)->getTable(), $foreign, $owner);
            if (isset($pivot_table, $pivot_owner, $pivot_foreign)) {
                $relation = PivotRelationInfo::fromRelationInfo($relation, $pivot_table, $pivot_owner, $pivot_foreign);
            }

            return $relation;
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function getColumns(bool $getTypes = false, bool $filterVisible = false, bool $filterWritable = false): array
    {
        $hidden = $this->getHiddenFields();
        $fillable = $this->getFillableFields();

        $columns = [];
        if (!$filterVisible) {
            array_push($columns, ...$hidden);
        }
        if (!$filterWritable) {
            array_push($columns, ...$fillable);
        }
        $columns = array_unique($columns);

        if ($getTypes) {
            $casts = $this->getModelCasts();
            $mapped_columns = [];
            foreach ($columns as $column) {
                $mapped_columns[$column] = array_key_exists($column, $casts) ? $casts[$column] : 'string';
            }

            $columns = $mapped_columns;
        }

        return $columns;
    }

    /**
     * @return (null|string)[]
     *
     * @psalm-return array{createdAt: null|string, updatedAt: null|string, deletedAt: null|string, lockedAt: null|string}
     */
    public static function getTimestampColumns(Model $model, bool $fullnames = false): array
    {
        $timestamps = [
            'createdAt' => null,
            'updatedAt' => null,
            'deletedAt' => null,
            'lockedAt' => null,
        ];

        /** @var Model $model */
        $prefix = $fullnames ? $model->getTable() . '.' : '';
        if ($model->timestamps) {
            $timestamps['createdAt'] = $prefix . $model->getCreatedAtColumn();
            $timestamps['updatedAt'] = $prefix . $model->getUpdatedAtColumn();
        }
        $uses = class_uses_recursive($model);
        if (method_exists($model, 'getDeletedAtColumn')) {
            $timestamps['deletedAt'] = $prefix . $model->getDeletedAtColumn();
        }
        if (in_array(HasLocks::class, $uses)) {
            /** @phpstan-ignore method.notFound  */
            $timestamps['lockedAt'] = $prefix . app('locked')->getLockedColumnName();
        }

        return $timestamps;
    }

    /**
     * @return string[]
     *
     * @psalm-return array<int, string>
     */
    public function getHiddenFields(): array
    {
        return $this->hidden ?? [];
    }

    /**
     * @return string[]
     *
     * @psalm-return array<string>
     */
    public function getFillableFields(): array
    {
        return $this->fillable ?? [];
    }

    /**
     * @return string[]
     *
     * @psalm-return array<string>
     */
    public function getModelCasts(): array
    {
        $casts = $this->casts;
        if (property_exists($this, 'dates')) {
            foreach ($this->dates as $date) {
                $casts[$date] = 'date';
            }
        }
        foreach ($this->getFillableFields() as $fillable) {
            if (!array_key_exists($fillable, $casts)) {
                $casts[$fillable] = 'string';
            }
        }
        foreach ($this->getTimestampColumns($this) as $name) {
            if ($name && !array_key_exists($name, $casts)) {
                $casts[$name] = 'date';
            }
        }

        return $casts;
    }

    public function getAppendFields(): array
    {
        return isset($this->appends) ? $this->appends : [];
    }

    private function getAppendAttributeMethod(string $operation, string $name): string
    {
        return $operation . Str::camel($name) . 'Attribute';
    }

    public function isAppend(string $name): bool
    {
        return in_array($name, $this->getAppendFields());
    }

    public function hasGetAppend(string $name): bool
    {
        return method_exists($this, $this->getAppendAttributeMethod('get', $name));
    }

    public function hasSetAppend(string $name): bool
    {
        return method_exists($this, $this->getAppendAttributeMethod('set', $name));
    }

    public function getGrid(): Grid
    {
        return new Grid($this);
    }

    public static function grid(): Grid
    {
        return new Grid(static::class);
    }
}
