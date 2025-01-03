<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Inspector\Inspect;
use Modules\Core\Casts\FilterOperator;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Components\Grid;
use Modules\Core\Grids\Components\Field;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Grids\Requests\GridRequest;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Grids\Casts\GridRequestData;
use function PHPUnit\Framework\assertInstanceOf;

/**
 * Main entity class with common properties
 * 
 */
abstract class Entity
{
    use HasPath;

    protected GridRequestData $requestData;

    protected Model $model;

    /** @var Collection<string, Field> */
    protected Collection $fields;

    /** @var Collection<string, Relation> */
    protected Collection $relations;

    /**
     * @param  Model|string  $model  related model name
     */
    public function __construct(Model|string $model)
    {
        $this->setModel($model);
        $this->fields = new Collection();
        $this->relations = new Collection();
    }

    //region [REQUEST]

    protected function parseRequest(GridRequest $request): void
    {
        $this->requestData = $request->parsed();

        // Log::debug($this->requestData->jsonSerialize());
    }

    //endregion

    //region [MODELS_TABLES]

    /**
     * checks if passed name is the current model name or a possibly related one
     */
    protected function isCurrentEntity(string $name): bool
    {
        $path = $this->getPath();

        return $name === $path || (strlen($path) > 0 ? ($path . '.') : $path) . $this->getName() === $name;
    }

    /**
     * return current entity table
     */
    public function getTable(): string
    {
        return $this->getModel()->getTable();
    }

    /**
     * get deeply all the tables
     *
     * @return Collection<int, string>
     */
    public function getAllTables(): Collection
    {
        $models = collect([$this->getTable()]);
        foreach ($this->getRelations() as $relation) {
            $models = $models->concat($relation->getAllTables());
        }

        return $models;
    }

    /**
     * gets model object
     *
     * @return Model
     * @phpstan-return Model&HasGridUtils
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * get deeply all the models
     *
     * @return Collection<int, Model>
     */
    public function getAllModels(): Collection
    {
        $models = collect([$this->getModel()]);
        foreach ($this->getRelations() as $relation) {
            $models = $models->concat($relation->getAllModels());
        }

        return $models;
    }

    /**
     * sets object model
     * 
     * @param Model|class-string<Model> $model
     * @return void
     */
    private function setModel(Model|string $model)
    {
        if (is_string($model)) {
            if (!is_subclass_of($model, Model::class)) {
                throw new \UnexpectedValueException('Only Model subclasses are compatible with Grid System');
            }

            $model = new $model;
        }

        if (!Grid::useGridUtils($model)) {
            if (!config('core.dynamic_gridutils')) {
                throw new \UnexpectedValueException('Model ' . $model::class . ' doesn\'t use ' . HasGridUtils::class);
            }

            // TODO: da verificare, solo imbastito
            $class = $model::class;
            $extended_class_name = Str::afterLast($class . config('core.extended_class_suffix'), '\\');
            $grid_utils = HasGridUtils::class;
            if (!class_exists($extended_class_name)) {
                eval("class $extended_class_name extends $class { use $grid_utils; }");
            }
            $model = new $extended_class_name;
        }

        $this->model = $model;
    }

    /**
     * gets the related Eloquent model class name
     */
    public function getModelName(): string
    {
        return class_basename($this->getModel());
    }

    /**
     * gets the related Eloquent model full class name with namespace
     */
    public function getFullModelName(): string
    {
        return $this->getModel()::class;
    }

    /**
     * gets primaryKey name
     */
    protected function getPrimaryKey(): string|array
    {
        return $this->getModel()->getKeyName();
    }

    /**
     * gets full primaryKey name
     */
    protected function getFullPrimaryKey(): string|array
    {
        return $this->getModel()->getQualifiedKeyName();
    }

    protected function hasSoftDelete(): bool
    {
        $model = $this->getModel();
        // @phpstan-ignore  method.notFound
        return !class_uses_trait($model, SoftDeletes::class) || !$model->isForceDeleting();
    }

    /**
     * @return (string)[]
     *
     * @psalm-return list{0?: null|string, 1?: null|string, 2?: mixed, 3?: mixed}
     */
    protected function getTimestampsColumns(): array
    {
        $model = $this->getModel();
        if (!$model->usesTimestamps()) {
            return [];
        }
        /** @var string[] $timestamps */
        $timestamps = [$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()];
        // @phpstan-ignore  method.notFound
        if (class_uses_trait($model, SoftDeletes::class) && $model->isForceDeleting()) {
            // @phpstan-ignore  method.notFound
            $timestamps[] = $model->getDeletedAtColumn();
        }
        if (class_uses_trait($model, HasLocks::class)) {
            // @phpstan-ignore  method.notFound
            $timestamps[] = app('locked')->getLockedColumnName();
        }

        return $timestamps;
    }

    //endregion

    //region [FIELDS]

    /**
     * search field into fields property
     *
     * @param  Field|string  $field  field object or name to search
     */
    public function getField(Field|string $field): ?Field
    {

        if (is_string($field)) {
            $splitted = explode('.', $field);
            $fieldname = array_pop($splitted);
            $fullname = $field;
        } else {
            $fieldname = $field->getName();
            $fullname = $field->getFullAlias();
        }

        if (!$this->getFields()->offsetExists($fieldname)) {
            return null;
        }
        $found_field = $this->getFields()->offsetGet($fieldname);

        return $found_field->getFullAlias() === $fullname ? $found_field : null;
    }

    /**
     * search field into fields property, relations and subrelations
     *
     * @param  Field|string  $field  field object or name to search
     */
    public function getFieldDeeply(Field|string $field): ?Field
    {
        $thisfield = $this->getField($field);
        if ($thisfield) {
            return $thisfield;
        }

        $prefix = $this instanceof Grid || $this instanceof Relation ? $this->getPath() : lcfirst($this->getModelName());
        $fieldpath = preg_replace('/^' . $prefix . "\./", '', is_string($field) ? preg_replace("/\.\w+$/", '', $field) : $field->getPath());
        if (!strlen($fieldpath)) {
            return null;
        }
        $exploded_fieldpath = explode('.', $fieldpath);
        if ($exploded_fieldpath[0] === lcfirst($this->getName())) {
            array_shift($exploded_fieldpath);
        }
        $top = array_shift($exploded_fieldpath);
        if (!$this->getRelations()->offsetExists($top)) {
            return null;
        }
        $subrelation = $this->getRelations()->offsetGet($top);

        return $subrelation->getFieldDeeply($field);
    }

    /**
     * get all the object fields
     *
     * @return Collection<string, Field>
     */
    public function getFields(?FieldType $type = null): Collection
    {
        return !$type ? $this->fields : $this->fields->filter(fn($field) => $field->getFieldType() === $type);
    }

    /**
     * get deeply all the fields
     *
     * @return Collection<string, Field>
     */
    public function getAllFields(?FieldType $type = null): Collection
    {
        $fields = (clone $this->getFields($type))->keyBy(fn($f) => $f->getFullName());
        foreach ($this->getRelations() as $relation) {
            $fields = $fields->merge($relation->getAllFields($type)->keyBy(fn($f) => $f->getFullName()));
        }

        return $fields;
    }

    /**
     * @psalm-return Collection<array-key, Field>
     */
    public function getAllQueryFields(): Collection
    {
        $fields = (clone $this->getFields())->filter(fn($field) => $field->getFieldType() !== FieldType::APPEND && $field->getFieldType() !== FieldType::METHOD)->keyBy(fn($f) => $f->getFullName());
        foreach ($this->getRelations() as $relation) {
            $fields = $fields->merge($relation->getAllQueryFields()->keyBy(fn($f) => $f->getFullName()));
        }

        return $fields;
    }

    /**
     * checks if current entity has any field
     */
    public function hasFields(?FieldType $type = null): bool
    {
        return $this->getFields($type)->isNotEmpty();
    }

    /**
     * checks if deep relations have any field
     */
    public function hasDeepFields(?FieldType $type = null): bool
    {
        foreach ($this->getRelations() as $relation) {
            if ($relation->hasFieldsDeeply($type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * checks if current entity or sub relations have any field
     */
    public function hasFieldsDeeply(?FieldType $type = null): bool
    {
        if ($this->getFields($type)->isNotEmpty()) {
            return true;
        }

        return $this->hasDeepFields($type);
    }

    /**
     * check if current entity has specified field
     */
    public function hasField(Field|string $field): bool
    {
        return $this->getField($field) !== null;
    }

    /**
     * checks if current entity or any deep relation has specified field
     */
    public function hasFieldDeeply(Field|string $field): bool
    {
        if ($this->hasField($field)) {
            return true;
        }

        return $this->getFieldDeeply($field) !== null;
    }

    /**
     * reset deeply entity fields removing all the others and setting the new ones
     *
     * @param Field[]|Collection<string, Field> $fields
     */
    protected function setFields(iterable $fields): void
    {
        if (!($fields instanceof Collection)) {
            $fields = collect($fields);
        }
        $this->fields = new Collection();
        $this->addFields($fields);
        $fields_keys = $this->getFields()->keys()->toArray();
        $filtered = $fields->filter(fn($field, $key) => !in_array($key, $fields_keys));

        foreach ($this->getRelations() as $relation) {
            $relation->setFields($filtered);
        }
    }

    /**
     * adds deeply a list of fields to current ojbect
     *
     * @param array<string,Field|\Closure(string): Field> $fields
     * @return void
     */
    protected function addFields(iterable $fields)
    {
        foreach ($fields as $name => &$field) {
            if ($field instanceof \Closure) {
                $path = explode('.', $name);
                array_pop($path);
                $path = implode('.', $path);
                if ($this->isCurrentEntity($path)) {
                    $field = $field($this->getModel());
                } else {
                    throw new \Exception('Cosa devo fare in questo caso?');
                }
            }
            assertInstanceOf(Field::class, $field);
            $this->addField($field);
        }
    }

    /**
     * adds field to current entity if not already exists
     *
     * @return bool inserted or not
     */
    public function addField(Field $field): bool
    {
        if ($this->hasField($field)) {
            return false;
        }
        $checked = false;
        $path = $field->getPath();
        if ($this->isCurrentEntity($path)) {
            $field->setModel($this->getModel());
            $this->getFields()->offsetSet($field->getName(), $field);
            $checked = true;
            // @phpstan-ignore method.notFound
        } elseif ($relation = $this->getModel()->getRelationshipDeeply($field->getPath())) {
            $this->addRelationField($relation, $field);
        }

        return $checked;
    }

    /**
     * add field the specified relation if not already exists
     *
     * @param  RelationInfo[]  $relationList  relation infos full path
     */
    private function addRelationField(array $relationList, Field $field): bool
    {
        $parent = $this->addRelationDeeply($relationList);

        return $parent->addField($field);
    }

    //endregion [FIELDS]

    //region [RELATIONS]

    /**
     * gets specified relation from entity if exists
     */
    public function getRelation(Relation|string $relation): ?Relation
    {
        $name = is_string($relation) ? $relation : $relation->getName();

        return $this->getRelations()->offsetExists($name) ? $this->getRelations()->offsetGet($name) : null;
    }

    /**
     * get specified relation from current entity or deep relations if exists
     * TODO: potrebbe indurre in un falso percorso se esiste lo stesso sub-nome in diverse sub-relations
     */
    public function getRelationDeeply(Relation|string $relation): ?Relation
    {
        $subfix = strlen($this->getPath()) ? preg_replace('/^' . $this->getPath() . "\./", '', $relation) : preg_replace('/^' . lcfirst($this->getModelName()) . './', '', $relation);
        $exploded = explode('.', $subfix);
        $first = array_shift($exploded);
        $thisrelation = $this->getRelation($first);
        if ($thisrelation && empty($exploded)) {
            return $thisrelation;
        }

        foreach ($this->getRelations() as $subrelation) {
            $thatrelation = $subrelation->getRelationDeeply(implode('.', $exploded));
            if ($thatrelation) {
                return $thatrelation;
            }
        }

        return null;
    }

    /**
     * gets all entity relations
     *
     * @return Collection<string, Relation>
     */
    public function getRelations(): Collection
    {
        return $this->relations;
    }

    /**
     * gets all relation paths
     *
     * @return Collection<int, string>
     */
    public function getAllFullRelationsNames(): Collection
    {
        $prefix = $this instanceof RelationInfo ? $this->getName() : lcfirst($this->getModelName());
        $relations = collect(!($this instanceof RelationInfo) ? [] : [$prefix]);
        foreach ($this->getRelations() as $relation) {
            $thisname = $relation->getFullName();
            $subnames = $relation->getAllFullRelationsNames();
            $relations->push($thisname);
            if ($subnames->isNotEmpty()) {
                $relations = $relations->concat($subnames);
            }
        }

        return $relations;
    }

    /**
     * checks if entity has relations configured
     */
    public function hasRelations(): bool
    {
        return $this->getRelations()->isNotEmpty();
    }

    /**
     * checks if entity has deep relations
     */
    public function hasDeepRelations(): bool
    {
        if ($this->hasRelations()) {
            foreach ($this->getRelations() as $relation) {
                if ($relation->hasRelations()) {
                    return true;
                }
                if ($relation->hasDeepRelations()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * check if entity has specified relation
     */
    public function hasRelation(Relation|string $relation): bool
    {
        return $this->getRelation($relation) !== null;
    }

    /**
     * checks if entity or deep relations have specified one
     */
    public function hasRelationDeeply(Relation|string $relation): bool
    {
        $relation_name = is_string($relation) ? $relation : $relation->getName();
        if ($this->hasRelation($relation_name)) {
            return true;
        }

        foreach ($this->getRelations() as $relation) {
            if ($relation->hasRelationDeeply($relation_name)) {
                return true;
            }
        }

        return false;
    }

    public function addRelationDeeply(array $relationList): static
    {
        $parent = $this;
        /** @var RelationInfo $relation */
        foreach ($relationList as $relation) {
            assertInstanceOf(RelationInfo::class, $relation);
            $subrelation = $parent->getRelation($relation->getName());
            if (!$subrelation) {
                $subrelation = new Relation($parent->getFullName(), $relation);
                $parent->addRelation($subrelation);
            }
            $parent = $subrelation;
        }

        return $parent;
    }

    /**
     * adds new relation it not already exists
     */
    public function addRelation(Relation $relation): bool
    {
        $relation_name = $relation->getName();
        if ($this->hasRelation($relation_name)) {
            return false;
        }
        $this->getRelations()->offsetSet($relation_name, $relation);

        return true;
    }

    /**
     * remove relation deeply with all related fields and subrelations
     *
     * @return bool deleted oor not
     */
    public function removeRelationDeeply(Relation|string $relation): bool
    {
        $relation_name = is_string($relation) ? $relation : $relation->getName();
        $all_relations = $this->getRelations();
        if ($this->hasRelation($relation_name)) {
            $all_relations->forget($relation_name);

            return true;
        }

        foreach ($all_relations as $relation) {
            if ($relation->removeRelationDeeply($relation_name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * removes deeplu relations without fields
     *
     * @return bool any relation has been deleted
     */
    public function removeUnusedRelations(): bool
    {
        $keys = $this->getRelations()->keys()->toArray();
        $removed = false;
        foreach ($keys as $key) {
            $relation = $this->getRelations()->get($key);
            $removed = $removed || $relation->removeUnusedRelations();
            if (!$relation->hasFields()) {
                $this->getRelations()->forget($key);
                $removed = true;
            }
        }

        return $removed;
    }

    //endregion [RELATIONS]

    //region [DEBUG]
    // protected static function dumpQuery(Builder $query): string
    // {
    // 	return vsprintf(str_replace('?', '%s', $query->toSql()), collect($query->getBindings())->map(function ($binding) {
    // 		return is_numeric($binding) ? $binding : "'{$binding}'";
    // 	})->toArray());
    // }
    //endregion [DEBUG]

    /**
     * Convert the model instance to an array.
     *
     * @return array[][]
     *
     * @psalm-return array{fields: array<string, array>}
     */
    public function toArray(): array
    {
        $mapped_fields = [];
        foreach ($this->getAllFields() as $f) {
            $mapped_fields[$f->getFullAlias()] = $f->toArray();
        }

        return [
            'fields' => $mapped_fields,
        ];
    }

    protected static function applyCorrectWhereMethod(Builder|Relation $query, Field|string $field, FilterOperator $operator, mixed $value, WhereClause $clause = WhereClause::AND): void
    {
        $fieldname = is_string($field) ? $field : $field->getName();
        $method = $clause === WhereClause::OR ? 'or' : '';
        $params = [$operator->value === 'like' ? DB::raw('LOWER(' . $fieldname . ')') : $fieldname];

        switch ($operator->value) {
            case 'in':
                $method = lcfirst($method . 'WhereIn');
                array_push($params, $value);
                break;
            default:
                if ($operator->value === '!=' && $value === null) {
                    $method = lcfirst($method . 'WhereNotNull');
                } elseif ($operator->value === '=' && $value === null) {
                    $method = lcfirst($method . 'WhereNull');
                } else {
                    $method = lcfirst($method . 'Where');
                    if ($operator->value === 'like' && is_string($value)) {
                        $percent_pos = Str::position($value, '%');
                        if ($percent_pos == false || ($percent_pos !== 0 && $percent_pos !== strlen($value) - 1)) {
                            $value = '%' . strtolower($value) . '%';
                        }
                    }
                    $params = [...$params, $operator->value, $value];
                }
        }

        $query->$method(...$params);
    }

    /**
     * @return (mixed|string)[]
     *
     * @psalm-return array<mixed|string>
     */
    protected function checkColumnsOrGetDefaults(Model $model, string $value_column, ?array $columns): array
    {
        if ($columns === null || ($columns == [$value_column] && $columns[0] === $model->getKeyName())) {
            $indexes = Inspect::indexes($model->getTable())->toArray();
            $columns = [...($columns === [$value_column] ? $columns : []), ...Arr::flatten(array_map(fn(array $idx) => $idx['columns'], $indexes))];
        }
        if (!in_array($value_column, $columns)) {
            array_unshift($columns, $value_column);
        }

        return $columns;
    }

    protected function addSortsIntoQuery(Builder|Relation $query, array $sorts): void
    {
        foreach ($sorts as $order) {
            if (Str::contains($order['property'], '.')) {
                $exploded = explode('.', $order['property']);
                $order['property'] = array_pop($exploded);
            }
            $query->orderBy($order['property'], $order['direction']);
        }
    }

    /**
     * @return (mixed|string)[][]
     *
     * @psalm-return array<array{property: mixed, direction: 'asc'}>
     */
    protected function getDefaultSorts(array $columns, Model $model): array
    {
        return array_map(fn($c) => ['property' => $c, 'direction' => 'asc'], array_filter($columns, fn($c) => $c !== $model->getKeyName()));
    }

    protected function setDataIntoResponse(ResponseBuilder $responseBuilder, Collection $data, int $totalRecords): void
    {
        $responseBuilder->setData($data);
        $responseBuilder->setCurrentRecords($data->count());
        $responseBuilder->setTotalRecords($totalRecords);
        if ($this->requestData->request->has('page') || $this->requestData->request->has('pagination')) {
            $responseBuilder->setCurrentPage((int) ($this->requestData->request->get('page') ?? 1));
            $responseBuilder->setPagination((int) $this->requestData->request->get('pagination'));
        } elseif ($this->requestData->request->has('from')) {
            $responseBuilder->setFrom($this->requestData->pagination['from']);
            $responseBuilder->setTo($this->requestData->pagination['to']);
        }
    }
}
