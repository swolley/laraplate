<?php

namespace Modules\Core\App\Http\Controllers;

use Closure;
use Throwable;
use BadMethodCallException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Doctrine\DBAL\Exception;
use Illuminate\Http\Request;
use InvalidArgumentException;
use UnexpectedValueException;
use Illuminate\Support\Carbon;
use Modules\Core\App\Casts\Sort;
use Modules\Core\App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Core\App\Casts\Column;
use Modules\Core\App\Casts\Filter;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Cache\CacheManager;
use Approval\Traits\RequiresApproval;
use Illuminate\Support\Facades\Cache;
use Modules\Core\App\Casts\ColumnType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Modules\Core\App\Casts\WhereClause;
use Modules\Core\App\Casts\FiltersGroup;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\App\Models\Modification;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\App\Casts\FilterOperator;
use Modules\Core\App\Casts\CrudRequestData;
use Modules\Core\App\Casts\ListRequestData;
use Modules\Core\App\Casts\TreeRequestData;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\App\Casts\IParsableRequest;
use Overtrue\LaravelVersionable\Versionable;
use Modules\Core\App\Casts\DetailRequestData;
use Modules\Core\App\Casts\ModifyRequestData;
use Modules\Core\App\Casts\SelectRequestData;
use Modules\Core\App\Helpers\ResponseBuilder;
use Modules\Core\App\Casts\HistoryRequestData;
use Symfony\Component\HttpFoundation\Response;
use Modules\Core\App\Helpers\PermissionChecker;
use Modules\Core\App\Http\Requests\ListRequest;
use Modules\Core\App\Http\Requests\TreeRequest;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\App\Http\Requests\DetailRequest;
use Modules\Core\App\Http\Requests\ModifyRequest;
use Modules\Core\App\Http\Requests\HistoryRequest;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Core\Locking\Exceptions\LockedModelException;
use Modules\Core\Locking\Exceptions\CannotUnlockException;
use Modules\Core\Locking\Exceptions\AlreadyLockedException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class CrudController extends Controller
{
    /**
     * checks if model uses recursive relationships trait
     */
    private function useRecursiveRelationships(Model $model): bool
    {
        return class_uses_trait($model, HasRecursiveRelationships::class);
    }

    /**
     * checks if model uses approval trait
     */
    private function useHasApproval(Model $model): bool
    {
        return class_uses_trait($model, RequiresApproval::class);
    }

    /**
     * checks if model uses versionable trait
     */
    private function hasHistory(Model $model): bool
    {
        return class_uses_trait($model, Versionable::class);
    }

    private function isParsableRequest(Request $request): bool
    {
        return in_array('Modules\Core\App\Casts\IParsableRequest', class_implements($request));
    }

    /**
     * @return string|mixed[]
     */
    private function getModelKeyValue(CrudRequestData $filters): string|array
    {
        /** @var string|string[] $key */
        $key = $filters->model->getKeyName();
        if (is_string($key)) {
            return $filters->$key;
        }
        $key_value = array_flip($key);
        foreach ($key as $k) {
            $key_value[$k] = $filters->$k;
        }

        return $key_value;
    }

    /**
     * @param  Closure(ResponseBuilder, SelectRequestData): ResponseBuilder  $operation
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     */
    private function executeOperation(Request|IParsableRequest $request, Closure $operation): Response
    {
        $response_builder = new ResponseBuilder($request);
        $filters = $this->isParsableRequest($request) ? $request->parsed() : $request->validated();

        try {
            $response_builder = $operation($response_builder, $filters);
        } catch (QueryException $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (LockedModelException $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_LOCKED);
        } catch (UnexpectedValueException | BadMethodCallException $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_BAD_REQUEST);
        } catch (\LogicException | AlreadyLockedException | CannotUnlockException $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_NOT_MODIFIED);
        } catch (ModelNotFoundException $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_NO_CONTENT);
        } catch (UnauthorizedException $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_UNAUTHORIZED);
        } catch (Throwable $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response_builder->json();
    }

    //region DA RENDERE COMUNE
    /**
     * removes non fillable request values from model
     *
     *
     * @return string[]
     *
     * @psalm-return list<non-empty-string>
     */
    private function removeNonFillableProperties(Model $model, array &$values): array
    {
        $fillables = $model->getFillable();
        $discarder_values = [];
        if (!empty($fillables)) {
            foreach (array_keys($values) as $property) {
                if (in_array($property, $fillables)) {
                    continue;
                }
                $discarder_values[] = "Discarder '$property', because is not a fillable property";
                unset($values[$property]);
            }
        }

        return $discarder_values;
    }

    /**
     * removes unnecessary unnecessary relationships
     */
    private function cleanRelations(array &$relations): void
    {
        // tengo parent commentato per ricordarmi che non va aggiunto
        $black_list = ['history', 'ancestors', 'ancestorsAndSelf', 'bloodline', 'children', 'childrenAndSelf', 'descendants', 'descendantsAndSelf'/*, 'parent'*/, 'parentAndSelf', 'rootAncestor', 'siblings', 'siblingsAndSelf'];
        $relations = array_filter($relations, fn($relation) => !in_array($relation, $black_list));
    }

    /**
     * @return (mixed|null|string)[]
     *
     * @psalm-return array{relation: string, connection: 'default'|mixed, table: mixed, field: null|string}
     */
    private function splitProperty(Builder|Model $model, string $property): array
    {
        /** @var string[] $exploded */
        $exploded = explode('.', $property);
        if (!empty($exploded) && $exploded[0] === $model->getTable()) {
            array_shift($exploded);
        }
        $field = array_pop($exploded);
        $relation = implode('.', $exploded);
        $relation_model = $model instanceof Model ? $model : $model->getModel();
        array_shift($exploded);
        while (!empty($exploded)) {
            $relation_model = $relation_model->{array_shift($exploded)}()->getModel();
        }

        return ['relation' => $relation, 'connection' => $relation_model->connection ?? 'default', 'table' => $relation_model->getTable(), 'field' => $field];
    }

    private function applyFilter(Builder $query, Filter $filter, string &$method, array &$relations_columns): void
    {
        if (substr_count($filter->property, '.') > 1) {
            // relations
            $splitted = $this->splitProperty($query->getModel(), $filter->property);
            $query->{$method . 'Has'}($splitted['relation'], function (Builder $q) use ($filter, $method, $splitted, $relations_columns) {
                if ($splitted['field'] === 'deleted_at') {
                    $permission = $splitted['connection'] . '.' . $splitted['table'] . '.' . 'delete';
                    $user = Auth::user();
                    if ($user->can($permission)) {
                        $q->withTrashed();
                    }
                }
                $cloned_filter = new Filter($splitted['field'], $filter->value, $filter->operator);
                $this->applyFilter($q, $cloned_filter, $method, $relations_columns);
            });
        } elseif ($filter->value == null) {
            // is or is not null
            $method = $method . ($filter->operator === FilterOperator::EQUALS ? 'Null' : 'NotNull');
            $query->$method($filter->property);
        } elseif (in_array($filter->operator, [FilterOperator::LIKE, FilterOperator::NOT_LIKE])) {
            // like not like
            $method = $method . Str::studly($filter->operator->value);
            $query->$method($filter->property, $filter->value);
        } else {
            // all the others
            $query->$method($filter->property, $filter->operator->value, $filter->value);
        }
    }

    // TODO: da rivedere
    private function recursivelyApplyFilters(Builder|Relation $query, FiltersGroup|array $filters, array $relation_columns): void
    {
        $iterable = is_Array($filters) && Arr::isList($filters) ? $filters : $filters->filters;
        $method = $filters->operator === WhereClause::AND ? 'where' : 'orWhere';
        foreach ($iterable as &$subfilter) {
            if (isset($subfilter->filters)) {
                $query->$method(fn(Builder $q) => $this->recursivelyApplyFilters($q, $subfilter, $relation_columns));
            } else {
                $this->applyFilter($query, $subfilter, $method, $relation_columns);
            }
        }
    }

    /**
     * @param  Column[]  $columns
     * @return void
     */
    private static function sortColumns(BuilderContract $query, array &$columns)
    {
        usort($columns, fn(Column $a, Column $b) => $a->name <=> $b->name);
        $all_columns_name = array_map(fn(Column $column) => $column->name, $columns);
        $primary_key = $query->getModel()->getKeyName();
        if (is_string($primary_key)) {
            $primary_key = [$primary_key];
        }
        foreach ($primary_key as $key) {
            if (!in_array($key, $all_columns_name)) {
                array_unshift($columns, new Column($key, ColumnType::COLUMN));
                $all_columns_name[] = $key;
            }
        }
    }

    /**
     * @param Builder|Relation $query
     * @param Column[] $relation_columns
     */
    private function applyColumnsToSelect(Builder|Relation $query, array &$relation_columns)
    {
        self::sortColumns($query, $relation_columns);
        $simple_columns = [];
        foreach ($relation_columns as $column) {
            if ($column->type === ColumnType::COLUMN) {
                $simple_columns[] = $column->name;
            }
        }
        $query->select($simple_columns);
    }

    /**
     * apply only direct aggregate relations on the current related entity
     */
    private function applyAggregatesToQuery(Builder|Relation $query, array &$relations_aggregates, string $relation)
    {
        foreach ($relations_aggregates as $aggregate_relation => $aggregates_cols) {
            if (preg_match('/^' . preg_quote($relation) . '\.\w+$/', $aggregate_relation) !== 1) continue;

            $subrelation = preg_replace('/^' . preg_quote($relation) . '\./', '', $aggregate_relation);
            foreach ($aggregates_cols as $col) {
                $method = 'with' . ucfirst($col->type->value);
                if ($col->type === ColumnType::SUM || $col->type === ColumnType::COUNT) {
                    $query->$method([$subrelation]);
                } else {
                    $query->$method([$subrelation . '.' . $col->name]);
                }
            }
            unset($relations_aggregates[$aggregate_relation]);
        }
    }

    private function createRelationCallback(Relation $query, string $relation, array &$relations_columns, array &$relations_sorts, array &$relations_aggregates, array &$relations_filters): void
    {
        if (!empty($relations_columns[$relation])) {
            $this->applyColumnsToSelect($query, $relations_columns[$relation]);
        }

        $this->applyAggregatesToQuery($query, $relations_aggregates, $relation);

        if (isset($relations_filters[$relation])) {
            $this->recursivelyApplyFilters($query, $relations_filters[$relation], $relations_columns[$relation]);
        }

        if (!empty($relations_sorts[$relation])) {
            foreach ($relations_sorts[$relation] as $sort) {
                $query->orderBy($sort->property, $sort->direction->value);
            }
        }
    }

    /**
     * @param  string[]  $relations
     * @param  array<array-key, Column[]>  $relations_columns
     * @param  array<string, Sort[]>  $relations_sorts
     */
    private function applyRelations(Builder $query, array $relations, array &$relations_columns, array &$relations_sorts, array &$relations_aggregates, array &$relations_filters): void
    {
        $merged_relations = array_unique(array_merge($relations, array_keys($relations_sorts), array_keys($relations_columns)));
        $this->cleanRelations($relations);

        // apply only direct aggregate relations on the main entity
        foreach ($relations_aggregates as $relation => $aggregates_cols) {
            if (strpos($relation, '.') !== false) continue;

            foreach ($aggregates_cols as $col) {
                $method = 'with' . ucfirst($col->type->value);
                if ($col->type === ColumnType::SUM || $col->type === ColumnType::COUNT) {
                    $query->$method([$relation]);
                } else {
                    $query->$method([$relation . '.' . $col->name]);
                }
            }
            unset($relations_aggregates[$relation]);
        }

        $withs = [];
        foreach ($merged_relations as $relation) {
            $withs[$relation] = function (Relation $q) use ($relation, $relations_columns, $relations_sorts, $relations_aggregates, $relations_filters) {
                $this->createRelationCallback($q, $relation, $relations_columns, $relations_sorts, $relations_aggregates, $relations_filters);
            };
        }
        $query->with($withs);
    }

    /**
     * @return non-empty-array<array-key, string>
     */
    private static function splitColumnNameOnLastDot(string $name): array
    {
        return preg_split('/\.(?=[^.]*$)/', $name, 2);
    }

    /** @return array{main: Column[], relations: array<string, Column[]>, aggregates: array<string, Column[]>} */
    private static function groupColumns(string &$mainEntity, array $columns_filters): array
    {
        $columns = [
            'main' => [],
            'relations' => [],
            'aggregates' => [],
        ];

        if (!empty($columns_filters)) {
            // used only for quick search instead of array_filter
            /** @var string[] $all_relations_names */
            $all_relations_names = [];

            /** @var object{name: string, type: ColumnType} $column */
            foreach ($columns_filters as $column) {
                $index = str_replace($mainEntity . '.', '', $column->name);
                if (preg_match("/^\w+\.\w+$/", $column->name) && $column->type === ColumnType::COLUMN) {
                    $columns['main'][] = new Column($index, $column->type);
                } else {
                    $splitted = self::splitColumnNameOnLastDot($index);
                    if (!isset($splitte[1])) {
                        $splitted[1] = '*';
                    }
                    if ($column->type === ColumnType::COLUMN) {
                        $remapped_column = new Column($splitted[1], $column->type);
                        if (!in_array($splitted[0], $all_relations_names)) {
                            $columns['relations'][$splitted[0]] = [$remapped_column];
                            $all_relations_names[] = $splitted[0];
                        } else {
                            $columns['relations'][$splitted[0]][] = $remapped_column;
                        }
                    } else if ($column->type->isAggregateColumn()) {
                        $cloned_column = new Column($splitted[1], $column->type);
                        if (!array_key_exists($index, $columns['aggregates'])) {
                            $columns['aggregates'][$splitted[0]] = [$cloned_column];
                        } else {
                            $columns['aggregates'][$splitted[0]][] = $cloned_column;
                        }
                    }
                }
            }
        }

        return $columns;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function prepareQuery(Builder $query, SelectRequestData $request_data): void
    {
        $main_model = $query->getModel();
        $main_entity = $main_model->getTable();
        $relations_sorts = [];
        $relations_columns = [];
        $relations_filters = [];

        $columns = self::groupColumns($main_entity, $request_data->columns);
        foreach ($columns as $type => $cols) {
            if ($type === 'main' && !empty($cols)) {
                self::sortColumns($query, $cols);
                $only_standard_columns = [];
                foreach ($cols as $column) {
                    if ($column->type === ColumnType::COLUMN) {
                        $only_standard_columns[] = $column->name;
                    }
                }
                // TODO: qui mancano ancora le colonne utili a fare le relation se la foreign key si trova sulla main table
                $query->select($only_standard_columns);
            } else if ($type === 'relations' && !empty($cols)) {
                foreach ($cols as $relation => $relation_cols) {
                    static::sortColumns($query, $relation_cols);
                    $only_relation_columns = [];
                    foreach ($relation_cols as $column) {
                        if ($column->type === ColumnType::COLUMN) {
                            $only_relation_columns[] = $column;
                        }
                    }
                    $relations_columns[$relation] = $only_relation_columns;
                    if (!in_array($relation, $request_data->relations)) {
                        $request_data->relations[] = $relation;
                    }
                }
            }
        }

        if ($request_data instanceof ListRequestData) {
            // check for sorts and prepare data
            if (isset($request_data->sort)) {
                foreach ($request_data->sort as $column) {
                    if (preg_match("/^\w+\.\w+$/", $column->property)) {
                        $query->orderBy($column->property, $column->direction->value);
                    } else {
                        $index = str_replace($main_entity . '.', '', $column->property);
                        $splitted = self::splitColumnNameOnLastDot($index);
                        $cloned_column = new Sort($splitted[1], $column->direction);
                        if (!array_key_exists($index, $columns['relations'])) {
                            $relations_sorts[$splitted[0]] = [$cloned_column];
                        } else {
                            $relations_sorts[$splitted[0]][] = $cloned_column;
                        }
                    }
                }
            }
            // if (isset($request_data->group_by)) {
            //     $request_data->group_by = array_map(fn (string $group) => str_replace($main_entity . '.', '', $group), $request_data->group_by);
            // }
        }

        if ($request_data instanceof ListRequestData && isset($request_data->filters)) {
            // TODO: come faccio a smontare filters e raggrupparlo per la singola relation?
            // forse devo fare un filter ricorsivo nell'oggetto FiltersGroup e tirare fuori solo i campi relativi alla singoal relation o sottorelation conservando la struttura originale?

            // foreach ($request_data->filters->filters as $filter) {
            //     if (!preg_match("/^\w+\.\w+$/", $filter->property)) {
            //         $index = str_replace($main_entity . '.', '', $filter->property);
            //         $splitted = self::splitColumnNameOnLastDot($index);
            //         $cloned_filter = new Filter($splitted[1], $filter->value, $filter->operator);
            //         $relation_name = preg_replace('/\.' . $splitted[1] . '$/', '', $filter->property);
            //         if (!array_key_exists($index, $relations_filters[$relation_name])) {
            //             $relations_filters[$relation_name] = [$cloned_filter];
            //         } else {
            //             $relations_filters[$relation_name][] = $cloned_filter;
            //         }
            //     }
            // }

            $this->recursivelyApplyFilters($query, $request_data->filters, $columns['relations']);
        }

        if (!empty($request_data->relations)) {
            $this->applyRelations($query, $request_data->relations, $relations_columns, $relations_sorts, $columns['aggregates'], $relations_filters);
        }
    }

    /**
     * @param  string[]  $groupBy
     * @return Collection
     */
    private function applyGroupBy(Collection &$data, array $groupBy)
    {
        if (empty($groupBy)) {
            return $data;
        }

        /** @psalm-suppress InvalidTemplateParam */
        return $data->groupBy($groupBy);
    }
    //endregion

    //region READ OPERATIONS

    private function listByPagination(Builder $query, ListRequestData $filters, ResponseBuilder $responseBuilder, int $totalRecords): Collection
    {
        $query->take($filters->pagination)->skip($filters->skip);
        $data = $query->get();
        $responseBuilder
            ->setTotalRecords($totalRecords)
            ->setCurrentRecords($data->count())
            ->setPagination($filters->pagination)
            ->setCurrentPage($filters->page)
            ->setTotalPages($filters->calculateTotalPages($totalRecords));

        return $data;
    }

    private function listByFromTo(Builder $query, ListRequestData $filters, ResponseBuilder $responseBuilder, int $totalRecords): Collection
    {
        $query->skip($filters->skip);
        if (isset($filters->to)) {
            $query->take($filters->take);
        }
        $data = $query->get();
        $responseBuilder
            ->setTotalRecords($totalRecords)
            ->setCurrentRecords($data->count())
            ->setFrom($filters->from)
            ->setTo($filters->to);

        return $data;
    }

    private function listByOthers(Builder $query, ListRequestData $filters, ResponseBuilder $responseBuilder, int $totalRecords): Collection
    {
        if (isset($filters->limit)) {
            $query->take($filters->take);
        }
        $data = $filters->count ? $totalRecords : $query->get();
        $responseBuilder
            ->setTotalRecords($totalRecords)
            ->setCurrentRecords(is_numeric($data) ? $data : $data->count());

        return $data;
    }

    /**
     * List the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function list(ListRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, ListRequestData $filters): ResponseBuilder {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return CacheManager::tryByRequest($model, $filters->request, function () use ($model, $filters, $response_builder) {
                $query = $model::query();
                $this->prepareQuery($query, $filters);

                $total_records = $query->count();
                if (isset($filters->page)) {
                    $data = $this->listByPagination($query, $filters, $response_builder, $total_records);
                } elseif (isset($filters->from)) {
                    $data = $this->listByFromTo($query, $filters, $response_builder, $total_records);
                } else {
                    $data = $this->listByOthers($query, $filters, $response_builder, $total_records);
                }

                if (isset($filters->group_by)) {
                    $data = $this->applyGroupBy($data, $filters->group_by);
                }

                return $response_builder
                    ->setClass($model)
                    ->setData($data)
                    ->setCachedAt(Carbon::now());
            });
        });
    }

    /**
     * Show the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function detail(DetailRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, DetailRequestData $filters): ResponseBuilder {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return CacheManager::tryByRequest($model, $filters->request, function () use ($model, $filters, $response_builder) {
                $query = $model::query();
                $this->prepareQuery($query, $filters);

                return $response_builder
                    ->setClass($model)
                    ->setData($query->sole())
                    ->setCachedAt(Carbon::now());
            });
        });
    }

    /**
     * Show resource history
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function history(HistoryRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, HistoryRequestData $filters): ResponseBuilder {
            $model = $filters->model;
            if (!$this->hasHistory($model)) {
                throw new BadMethodCallException("'$filters->mainEntity' doesn't have history handling");
            }
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return CacheManager::tryByRequest($model, $filters->request, function () use ($model, $filters, $response_builder) {
                $query = $model::query();
                $this->prepareQuery($query, $filters);
                $query->with('history', function (Builder $q) use ($filters) {
                    $q->latest();
                    if (isset($filters->limit)) {
                        $q->take($filters->limit);
                    }
                });
                if (!preview() && $this->useHasApproval($model)) {
                    $query->with('modifications');
                }

                return $response_builder
                    ->setClass($model)
                    ->setData($query->sole())
                    ->setCachedAt(Carbon::now());
            });
        });
    }

    /**
     * Get the specified resource data
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function tree(TreeRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, TreeRequestData $filters): ResponseBuilder {
            $model = $filters->model;
            if (!$this->useRecursiveRelationships($model)) {
                throw new UnexpectedValueException("'$filters->mainEntity' is not a hierarchical class");
            }
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return CacheManager::tryByRequest($model, $filters->request, function () use ($model, $filters, $response_builder) {
                $tree_relation_type = [];
                if ($filters->parents && $filters->children) {
                    $tree_relation_type = 'bloodline';
                } elseif ($filters->parents) {
                    $tree_relation_type = 'ancestorsAndSelf';
                } elseif ($filters->children) {
                    $tree_relation_type = 'descendantsAndSelf';
                }

                $query = $model::with($tree_relation_type);
                $this->prepareQuery($query, $filters);

                return $response_builder
                    ->setClass($model)
                    ->setData($query->sole())
                    ->setCachedAt(Carbon::now());
            });
        });
    }

    //endregion

    //region WRITE OPERATIONS

    private function removeNotFillableProperties(Model $model, array &$values): array
    {
        $non_fillables = [];
        $non_fillables = array_diff(array_keys($model->getFillable()), array_keys($values));
        foreach ($non_fillables as $property) {
            unset($values[$property]);
        }

        return $non_fillables;
    }

    /**
     * Insert the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function insert(Request $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, ModifyRequestData $values, $request): ResponseBuilder {
            $model = $values->model;
            PermissionChecker::ensurePermissions($request, $model->getTable(), 'insert', $model->getConnectionName());
            // $values = method_exists($model, 'getRules') ? $request->validate($model->getRules()) : $filters;
            // $values = $request->all();
            // se ci sono proprietà che non sono nei fillable devo restituire errore
            $discarded_values = $this->removeNotFillableProperties($model, $values->changes);

            $created = $model->create($values->changes);
            if (!$created) {
                throw new \LogicException('Record not created');
            }

            $created->fresh();

            return $response_builder
                ->setData($created)
                ->setStatus(Response::HTTP_CREATED)
                ->setError(!empty($discarded_values) ? $discarded_values : null);
        });
    }

    /**
     * Update the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function update(ModifyRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, ModifyRequestData $values): ResponseBuilder {
            $model = $values->model;
            PermissionChecker::ensurePermissions($values->request, $model->getTable(), 'update', $model->getConnectionName());
            // if $filters->request->method() == 'PUT' devo sovrascrivere tutto il record quindi devono esserci tutti i fillable e devo fare le validazioni
            // else valido quello che ho con le regole che ho se le ho
            if ($model->usesTimestamps() && !isset($values->{$model::UPDATED_AT})) {
                throw new BadMethodCallException($model::UPDATED_AT . ' field is required when updating an entity that uses timestamps');
            }
            $key_value = $this->getModelKeyValue($values);
            $found_records = $model->where($key_value)->get();
            $discarded_values = $this->removeNonFillableProperties($model, $values->changes);

            // TODO: come gestire la preview del record? E se ci sono modifiche in pending cosa devo fare?
            // 1) impedisco la modifica finché non è approvato/disapprovato tutto
            // 2) posso mettere un flag "force" che disapprova le modifiche in pending e ne crea una nuova?

            // se ci sono proprietà che non sono nei fillable devo restituire errore

            if ($found_records->isEmpty() && $values->request->has('id')) {
                throw new ModelNotFoundException('No model Found');
            }
            $updated_records = new Collection();
            DB::transaction(function () use ($found_records, $updated_records, $values) {
                foreach ($found_records as $found_record) {
                    /** @psalm-suppress InvalidArgument */
                    if ($found_record->update($values->changes)) {
                        $updated_records->add($found_record->fresh());
                    }
                }
            });

            if (!empty($discarded_values)) {
                $response_builder->setError($discarded_values);
            }

            return $response_builder->setData($updated_records);
        });
    }

    /**
     * Remove the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function delete(ModifyRequest $request): Response
    {
        // delete deve bypassare le preview
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, CrudRequestData $filters): ResponseBuilder {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'forceDelete', $model->getConnectionName());
            $key_value = $this->getModelKeyValue($filters);
            $found_records = $model->where($key_value)->get();

            if ($found_records->isEmpty() && $filters->request->has('id')) {
                throw new ModelNotFoundException('No model Found');
            }
            $deleted_records = new Collection();
            DB::transaction(function () use ($found_records, $deleted_records) {
                foreach ($found_records as $found_record) {
                    if ($found_record->forceDelete()) {
                        $deleted_records->add($found_record);
                    }
                }
            });

            return $response_builder->setData($deleted_records);
        });
    }

    /**
     * @param  "activate"|"inactivate"  $operation
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     */
    public function doActivateOperation(ModifyRequest $request, string $operation): Response
    {
        // activate deve bypassare le preview
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, CrudRequestData $filters, $request) use ($operation): ResponseBuilder {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($request, $model->getTable(), 'restore', $model->getConnectionName());
            $key = $filters->primaryKey;
            $key_value = is_string($key) ? $filters->$key : array_map(fn($k) => $filters->$k, $key);
            $found_record = $model->withTrashed()->findOrFail($key_value);
            if ($operation === 'activate' && !$found_record->restore()) {
                throw new \LogicException('Record not activated');
            } elseif (!$found_record->delete()) {
                throw new \LogicException('Record not inactivated');
            }

            $found_record->fresh();

            return $response_builder->setData($found_record);
        });
    }

    /**
     * Logically restore the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function activate(ModifyRequest $request): Response
    {
        return $this->doActivateOperation($request, 'activate');
    }

    /**
     * Logically delete the specified resource
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     */
    public function inactivate(ModifyRequest $request): Response
    {
        return $this->doActivateOperation($request, 'inactivate');
    }

    /**
     * @param  "approve"|"disapprove"  $operation
     */
    private function doApproveOperation(ModifyRequest $request, string $operation): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, CrudRequestData $filters, $request) use ($operation): ResponseBuilder {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($request, $model->getTable(), 'approve', $model->getConnectionName());
            /** @var string|array $key */
            $key = $model->getKeyName();
            $key_value = is_string($key) ? $filters->$key : array_map(fn($k) => $filters->$k, $key);
            $found_record = $model->withTrashed()->findOrFail($key_value);
            /** @var User $user */
            $user = Auth::user();
            if ($filters['modification']) {
                $modification = Modification::where(['modifiable_type' => $model::class, 'modifiable_id' => $filters->primaryKey])->findOrFail($filters['modification']);
                if ($operation === 'approve') {
                    $user->approve($modification);
                } else {
                    $user->disapprove($modification);
                }
            } else {
                $modifications = $model::findOrFail($filters->primaryKey)->modifications()->activeOnly()->oldest()->get();
                if ($modifications->isEmpty()) {
                    throw new \LogicException("No modifications to be {$operation}d");
                }
                foreach ($modifications as $modification) {
                    if ($operation === 'approve') {
                        $user->approve($modification);
                    } else {
                        $user->disapprove($modification);
                    }
                }
            }

            $found_record->refresh();

            return $response_builder->setData($found_record);
        });
    }

    /**
     * Approve current pending record modifications
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function approve(ModifyRequest $request): Response
    {
        return $this->doApproveOperation($request, 'approve');
    }

    /**
     * Register user disapprovation for pending record modifications
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function disapprove(ModifyRequest $request): Response
    {
        return $this->doApproveOperation($request, 'disapprove');
    }

    /**
     * @param  "lock"|"unlock"  $operation
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     */
    private function doLockOperation(ModifyRequest $request, string $operation): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, CrudRequestData $filters) use ($operation): ResponseBuilder {
            $model = $filters->model;
            if (!class_uses_trait($model, HasLocks::class)) {
                throw new BadMethodCallException($model::class . ' doesn\'t support locks');
            }
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'lock', $model->getConnectionName());
            $key_value = $this->getModelKeyValue($filters);
            $found_records = $model->where($key_value)->get();

            if ($found_records->isEmpty() && $filters->request->has('id')) {
                throw new ModelNotFoundException('No model Found');
            }
            $can_be_done = ($operation === 'lock' && $found_records->first()->isLocked()) || !$found_records->first()->isLocked();
            if ($found_records->count() === 1 && $filters->request->has('id') && $can_be_done) {
                throw new AlreadyLockedException($operation === 'lock' ? 'Record already locked' : 'Record isn\'t locked');
            }
            $locked_records = new Collection();
            DB::transaction(function () use ($found_records, $locked_records) {
                foreach ($found_records as $found_record) {
                    /** @psalm-suppress InvalidArgument */
                    if (!$found_record->isLocked() && $found_record->lock()) {
                        $locked_records->add($found_record->fresh());
                    }
                }
            });

            return $response_builder->setData($locked_records);
        });
    }

    /**
     * Lock resource
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     */
    public function lock(ModifyRequest $request): Response
    {
        return $this->doLockOperation($request, 'lock');
    }

    /**
     * Unlock resource
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     */
    public function unlock(ModifyRequest $request): Response
    {
        return $this->doLockOperation($request, 'unlock');
    }

    // TODO: non ho creato la rotta perché se creo file di modelli e poi rifaccio un deploy li perderei
    // public function mapModel(Request $request, string $entity
    // {
    //     return $this->executeOperation($request, $entity, function (ResponseBuilder $response_builder, Model $model, array $filters) use ($entity) {
    //         if ($model instanceof DynamicEntity) throw new \LogicException("A model for '$entity' entity already exists");
    //         $table = $model->getTable();
    //         Artisan::call("make:model {$table}");

    //         $model_class = $model::class;
    //         return (new ResponseBuilder())->setData("Model $model_class persisted to filesystem")->setStatus(Response::HTTP_CREATED);
    //     });
    // }

    /**
     * Clear model cache
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     */
    public function clearModelCache(Request $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, CrudRequestData $filters): ResponseBuilder {
            $model = $filters->model;
            $table = $model->getTable();
            Cache::tags([$table])->flush();

            return $response_builder->setData("$table cached cleared")->setStatus(Response::HTTP_OK);
        });
    }

    //endregion
}
