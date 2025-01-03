<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Components;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Cache\HasCache;
use PHPUnit\Framework\Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Cache\CacheManager;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\FilterOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Modules\Core\Grids\Casts\GridAction;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Grids\Definitions\Entity;
use Modules\Core\Grids\Hooks\HasReadHooks;
use Modules\Core\Grids\Hooks\HasWriteHooks;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Grids\Requests\GridRequest;
use Doctrine\DBAL\Exception as DBALException;
use Modules\Core\Grids\Casts\GridRequestData;
use Modules\Core\Grids\Resources\ResponseBuilder;
use PHPUnit\Framework\ExpectationFailedException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\Core\Grids\Exceptions\ConcurrencyException;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;

class Grid extends Entity
{
    use HasReadHooks, HasWriteHooks;

    private bool $initialized = false;

    private array $forcedFields = [];

    private array $prepareFieldsCallbacks = [];

    const LAYOUTS_TABLE = 'grid_layouts';

    public function __construct(Model|string $model)
    {
        parent::__construct($model);
        $this->name = lcfirst($this->getModelName());
    }

    public static function useGridUtils(Model $model): bool
    {
        return class_uses_trait($model, HasGridUtils::class);
    }

    //region [CONFIGS]

    /**
     * @throws \InvalidArgumentException
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    private function withTrashed(): bool
    {
        if (!$this->hasSoftDelete()) {
            return false;
        }
        $user = Auth::user();

        return $user && $user->can($this->getModel()->getTable() . '.delete');
    }

    /**
     * @throws \Exception
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws UnknownClassOrInterfaceException
     * @throws DBALException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function getConfigs(): array
    {
        $this->checkGridConfigs();

        return $this->toArray();
    }

    /**
     * append column into grid configs fields and checks as forcedField
     */
    private function appendFieldIntoList(array &$necessary_fields, string $column, bool $force = false): bool
    {
        if (!array_key_exists($column, $necessary_fields) && !$this->hasFieldDeeply($column)) {
            $necessary_fields[$column] = Field::create($column);
            if ($force) {
                $this->forcedFields[] = $column;
            }

            return true;
        }

        return false;
    }

    /**
     * complete grid properties with request data
     *
     * @return void
     *
     * @throws Exception
     * @throws ExpectationFailedException
     */
    private function completeFromRequestData(array &$necessary_fields)
    {
        if ($columns = $this->requestData->columns) {
            foreach ($columns as $column) {
                $this->appendFieldIntoList($necessary_fields, $column->name, false);
            }
        }

        if ($relations = $this->requestData->relations) {
            foreach ($relations as $relation) {
                if (!$this->hasRelationDeeply($relation)) {
                    // @phpstan-ignore method.notFound
                    $relation_info = $this->getModel()->getRelationshipDeeply($relation);
                    $this->addRelationDeeply($relation_info);
                }
            }
        }

        if ($filters = $this->requestData->filters) {
            foreach (array_keys($filters->filters) as $column) {
                $this->appendFieldIntoList($necessary_fields, $column, true);
            }
        }

        if ($options = $this->requestData->optionsFilters) {
            foreach (array_keys($options) as $column) {
                $this->appendFieldIntoList($necessary_fields, $column, true);
            }
        }

        if ($funnels = $this->requestData->funnelsFilters) {
            foreach (array_keys($funnels) as $column) {
                $this->appendFieldIntoList($necessary_fields, $column, true);
            }
        }

        if ($orders = $this->requestData->sort) {
            foreach ($orders as $order) {
                $this->appendFieldIntoList($necessary_fields, $order['property'], true);
            }
        }
    }

    /**
     * check grid configs and calls inject-fields methods
     *
     * @return void
     */
    private function checkGridConfigs()
    {
        try {
            if ($this->initialized) {
                return;
            }

            if (!$this->hasFieldsDeeply() && !isset($this->requestData)) {
                $this->getDefaultModelFields();
            } else {
                $necessary_fields = [];
                $options = null;
                $funnels = null;

                if (isset($this->requestData)) {
                    $action = $this->requestData->action;
                    if ($action === GridAction::CHECK /*|| $action === GridAction::LAYOUT*/) {
                        return;
                    }
                    $this->completeFromRequestData($necessary_fields);
                }

                // force injection of necessary fields (primay, timestamps)
                if (!empty($necessary_fields)) {
                    $this->addNecessaryFields($necessary_fields);
                }
                $necessary_fields = array_filter($necessary_fields, fn($name) => !$this->hasField($name), ARRAY_FILTER_USE_KEY);
                $this->initFieldsByConfigs($necessary_fields);
                $necessary_fields = array_map(fn($generator) => $generator($this->getModel()), $necessary_fields);
                $this->addFields($necessary_fields);

                if ($options) {
                    $this->initOptions($options);
                }
                if ($funnels) {
                    $this->initFunnels($funnels);
                }
            }
        } finally {
            $this->initialized = true;
        }
    }

    /**
     * @throws DBALException
     * @throws \Exception
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws UnknownClassOrInterfaceException
     * @throws \UnexpectedValueException
     */
    private function initOptions(array $options): void
    {
        foreach ($options as $column => $option_data) {
            $field = $this->getFieldDeeply($column);
            if (!$field) {
                continue;
            }

            $prefix = $field->getPath();
            $label = $option_data['label'] ?? null;
            $model = $field->getModel();
            if (!$label && $model) {
                $label = $this->checkColumnsOrGetDefaults($field->getModel(), $field->getName(), $option_data['columns'] ?? [$field->getName()]);
            }

            $field->options(Option::create($label));
            $option = $field->getOption();
            if (isset($option_data['columns'])) {
                $additional_fields = [];
                foreach ($option_data['columns'] as $column) {
                    if (!Str::contains($column, '.')) {
                        $column = $prefix . '.' . $column;
                    }
                    $additional_fields[$column] = Field::create($column, writable: false);
                }
                $option->addFields($additional_fields);
            }
        }
    }

    /**
     * @throws DBALException
     * @throws \Exception
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws UnknownClassOrInterfaceException
     * @throws \UnexpectedValueException
     */
    private function initFunnels(array $funnels): void
    {
        foreach ($funnels as $column => $funnel_data) {
            $field = $this->getFieldDeeply($column);
            if (!$field) {
                continue;
            }

            $prefix = $field->getPath();
            $label = $funnel_data['label'] ?? null;
            $model = $field->getModel();
            if (!$label && $model) {
                $label = $this->checkColumnsOrGetDefaults($field->getModel(), $field->getName(), $funnel_data['columns'] ?? [$field->getName()]);
            }

            $field->funnel(Funnel::create($label));
            $funnel = $field->getFunnel();
            if (isset($funnel_data['columns'])) {
                $additional_fields = [];
                foreach ($funnel_data['columns'] as $column) {
                    if (!Str::contains($column, '.')) {
                        $column = $prefix . '.' . $column;
                    }
                    $additional_fields[] = Field::create($column, writable: false);
                }
                $funnel->addFields($additional_fields);
            }
        }
    }

    /**
     * inject all model fields if no configuration is set in controller
     *
     * @return void
     */
    private function getDefaultModelFields()
    {
        Log::debug('No configs for grid found, fallback to default injection');

        $all_fields = [];
        $this->addDefaultReadableFields($all_fields);
        $this->addDefaultWritableFields($all_fields);
        $this->addAppendFieldsToDefaults($all_fields);
        $this->addNecessaryFields($all_fields);
        $this->initFieldsByConfigs($all_fields);
        $this->setFields(array_values($all_fields));
    }

    private function addAndScrollDefaultFields(array &$allFields, bool $readable): void
    {
        $model = $this->getModel();
        $fields = $model->getColumns();
        foreach ($fields as $name => $type) {
            if (array_key_exists($name, $allFields)) {
                $allFields[$name][$readable ? 'readable' : 'writable'] = true;
            } else {
                $allFields[$name] = ['validation' => [$type], 'readable' => $readable, 'writable' => !$readable];
            }
        }
    }

    /**
     * get all readable fields and appends configs to array of default fields
     *
     * @return void
     */
    private function addDefaultReadableFields(array &$allFields)
    {
        $this->addAndScrollDefaultFields($allFields, true);
    }

    /**
     * get all writable fields and appends configs to array of default fields
     *
     * @return void
     */
    private function addDefaultWritableFields(array &$allFields)
    {
        $this->addAndScrollDefaultFields($allFields, false);
    }

    /**
     * get all calculated fields in Model and appends configs to array of default fields
     *
     * @return void
     */
    private function addAppendFieldsToDefaults(array &$allFields)
    {
        $appends = $this->getModel()->getAppendFields();
        foreach ($appends as $name) {
            if (!array_key_exists($name, $allFields)) {
                $allFields[lcfirst($this->getModelName()) . '.' . $name] = ['validation' => [], 'readable' => true, 'writable' => false];
            }
        }
    }

    /**
     * call addTimestampsFields and addPrimaryKey methods
     *
     * @return void
     */
    private function addNecessaryFields(array &$allFields)
    {
        $this->addTimestampsFields($allFields);
        $this->addPrimaryKey($allFields);
    }

    /**
     * get all timestamps fields and appends configs to array of passed one
     *
     * @return void
     */
    private function addTimestampsFields(array &$allFields)
    {
        $timestamps = $this->getTimestampsColumns();
        foreach ($timestamps as $name) {
            $allFields[lcfirst($this->getModelName()) . '.' . $name] = ['validation' => ['date', 'filled'], 'readable' => true, 'writable' => true];
        }
    }

    /**
     * get primary key field/s and appends configs to array of passed one
     *
     * @return void
     */
    private function addPrimaryKey(array &$allFields)
    {
        $primary_key_name = Arr::wrap($this->getPrimaryKey());
        $primary_key_type = $this->getModel()->getKeyType();
        $is_autoincremental = $this->getModel()->incrementing;
        foreach ($primary_key_name as $name) {
            $full_name = lcfirst($this->getModelName()) . '.' . $name;
            if (!array_key_exists($full_name, $allFields)) {
                $allFields[$full_name] = ['validation' => [$primary_key_type, 'filled'], 'readable' => true, 'writable' => !$is_autoincremental];
            } else {
                $allFields[$full_name]['readable'] = true;
                $allFields[$full_name]['writable'] = !$is_autoincremental;
            }
        }
    }

    //endregion [CONFIGS]

    //region [FIELDS]

    /**
     * alias of setFields for pipe
     *
     * @param array<\Closure(Model $model): Field>
     */
    public function fields(\Closure ...$fields)
    {
        $this->prepareFieldsCallbacks = $fields;
    }

    /**
     * @throws \Exception
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws UnknownClassOrInterfaceException
     * @throws \UnexpectedValueException
     * @throws BindingResolutionException
     */
    private function prepareFields(): void
    {
        $fields = array_map(fn($generator) => $generator($this->getModel()), $this->prepareFieldsCallbacks);
        $this->setFields($fields);
        $this->prepareNecessaryFields();
    }

    /**
     * @throws BindingResolutionException
     * @throws \Exception
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws UnknownClassOrInterfaceException
     * @throws \UnexpectedValueException
     */
    private function prepareNecessaryFields(): void
    {
        $necessary_fields = [];
        $this->addNecessaryFields($necessary_fields);
        foreach ($necessary_fields as $name => &$config) {
            $config = Field::create($name, readable: $config['readable'], writable: $config['writable']);
        }
        $fields = array_map(fn($generator) => $generator($this->getModel()), array_values($necessary_fields));
        $this->setFields($fields);
    }

    /**
     * call Field constructors from list of configs
     *
     * @return void
     */
    private function initFieldsByConfigs(array &$list)
    {
        $model_class = lcfirst($this->getModelName());
        foreach ($list as $name => &$field_info) {
            $field_info = is_callable($field_info) ? $field_info : Field::create(Str::startsWith($name, $model_class) ? $name : "$model_class.$name", readable: $field_info['readable'], writable: $field_info['writable']);
        }
    }

    //endregion [FIELDS]

    //region [VALIDATIONS]

    //endregion [VALIDATIONS]

    //region [EXECUTION]

    /**
     * start processing Grid request
     */
    public function process(GridRequest|GridRequestData $request): JsonResponse
    {
        if ($request instanceof GridRequest) {
            $this->parseRequest($request);
        } else {
            $this->requestData = $request;
        }
        $this->checkGridConfigs();

        $response_builder = new ResponseBuilder($request);
        $response_builder->setAction($this->requestData->action->value);
        $response_builder->setPrimaryKey($this->getModel()->getKeyName());

        foreach ($this->getModel()::getTimestampColumns($this->getModel()) as $key => $value) {
            $method = 'set' . ucfirst(preg_replace('/At$/', '', $key));
            $response_builder->$method($value);
        }

        $action = $this->requestData->action;
        // TODO: solo imbastito, da testare
        if ($action !== GridAction::CHECK) {
            // if ($action !== GridAction::LAYOUT) {
            $this->prepareFields();
            // } else {
            // $this->prepareNecessaryFields();
            // }
        }

        if (GridAction::isReadAction($this->requestData->action, $request->getMethod())) {
            // validation rules
            if ($this->requestData->action === GridAction::SELECT) {
                $all_rules = [];
                foreach ($this->getAllFields() as $name => $field) {
                    $rules = $field->getRules();
                    if (!empty($rules)) {
                        $all_rules[$name] = $rules;
                    }
                }

                $response_builder->setRules($all_rules);
            }

            $response_builder = $this->processReadActions($response_builder, $request);
        } else {
            $response_builder = $this->processWriteActions($response_builder, $request);
        }

        return $response_builder->json();
    }

    /**
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws BindingResolutionException
     * @throws \Throwable
     * @throws \UnexpectedValueException
     * @throws BadRequestException
     * @throws SuspiciousOperationException
     * @throws QueryException
     */
    private function callbackToReadAction(ResponseBuilder $responseBuilder, Request $request): ResponseBuilder
    {
        $action = $this->requestData->action;
        // if (!$action) {
        //     throw new \UnexpectedValueException('Unexpected action');
        // }

        // concurrency checks
        if ($action === GridAction::CHECK) {
            return $this->processConcurrencies($responseBuilder);
        }

        // layouts
        /** @phpstan-ignore classConstant.notFound */
        if ((/*$action === GridAction::LAYOUT &&*/$request->getMethod() === Request::METHOD_GET)) {
            return $this->processLayouts($responseBuilder);
        }

        // $promises = [];

        // data
        if (in_array($action, [GridAction::SELECT, GridAction::DATA])) {
            $this->processData($responseBuilder);
        }

        // options
        if ($action === GridAction::SELECT || $action === GridAction::OPTIONS) {
            $this->processOptions($responseBuilder);
        }

        // funnels
        if ($action === GridAction::SELECT || $action === GridAction::FUNNELS) {
            $this->processFunnels($responseBuilder);
        }

        // $promises->await();

        return $responseBuilder;
    }

    /**
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws BadRequestException
     * @throws SuspiciousOperationException
     * @throws QueryException
     */
    private function processReadActions(ResponseBuilder $responseBuilder, GridRequest $request): ResponseBuilder
    {
        if (class_uses_trait($this->getModel(), HasCache::class)) {
            return CacheManager::tryByRequest($this->getModel(), $request, fn($cbRequest) => $this->callbackToReadAction($responseBuilder, $cbRequest));
        }

        return $this->callbackToReadAction($responseBuilder, $request);
    }

    /**
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws BindingResolutionException
     * @throws \Throwable
     * @throws \UnexpectedValueException
     * @throws BadRequestException
     */
    private function processData(ResponseBuilder $responseBuilder): ResponseBuilder
    {
        [$data, $total_records] = $this->getData();
        $this->setDataIntoResponse($responseBuilder, $data, $total_records);
        $responseBuilder->setClass($this->getModel());
        $responseBuilder->setTable($this->getModel()->getTable());

        return $responseBuilder;
    }

    private function processOptions(ResponseBuilder $responseBuilder): ResponseBuilder
    {
        $data = $this->getOptionsData();
        $responseBuilder->setOptions($data);

        return $responseBuilder;
    }

    private function processFunnels(ResponseBuilder $responseBuilder): ResponseBuilder
    {
        $responseBuilder->setFunnels($this->getFunnelsData());

        return $responseBuilder;
    }

    /**
     * @throws QueryException
     * @throws \InvalidArgumentException
     */
    private function processLayouts(ResponseBuilder $responseBuilder): ResponseBuilder
    {
        $responseBuilder->setLayouts($this->getUserLayouts());

        return $responseBuilder;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws BindingResolutionException
     * @throws \Throwable
     * @throws \UnexpectedValueException
     */
    private function processConcurrencies(ResponseBuilder $responseBuilder): ResponseBuilder
    {
        $data = $this->checkRecordsConcurrency();
        $responseBuilder->setData($data);
        $responseBuilder->setTotalRecords($data->count());
        if ($data->isNotEmpty()) {
            $responseBuilder->setError(new ConcurrencyException());
        }

        return $responseBuilder;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws BadRequestException
     * @throws SuspiciousOperationException
     * @throws \BadMethodCallException
     * @throws \Exception
     * @throws \UnexpectedValueException
     * @throws BindingResolutionException
     * @throws \Throwable
     */
    private function processWriteActions(ResponseBuilder $responseBuilder, GridRequest $request): ResponseBuilder
    {
        $data = match ($this->requestData->action) {
            /** @phpstan-ignore classConstant.notFound */
            // GridAction::LAYOUT && $request->getMethod() === Request::METHOD_POST => $this->createUserLayout(),
            /** @phpstan-ignore classConstant.notFound, classConstant.notFound */
            // GridAction::LAYOUT && in_array($request->getMethod(), [Request::METHOD_PUT, Request::METHOD_PATCH]) => $this->updateUserLayout(),
            /** @phpstan-ignore classConstant.notFound */
            // GridAction::LAYOUT && $request->getMethod() === Request::METHOD_DELETE => $this->deleteUserLayout(),
            GridAction::INSERT => $this->createRecord(),
            GridAction::UPDATE => $this->updateRecords(),
            GridAction::DELETE => $this->softDeleteRecords(),
            GridAction::FORCE_DELETE => $this->forceDeleteRecords(),
                // GridAction::RESTORE => $this->restoreRecords(),
            default => throw new \InvalidArgumentException('Not a valid action'),
        };

        $responseBuilder->setData($data);
        /** @phpstan-ignore classConstant.notFound */
        if ($this->requestData->action === GridAction::INSERT) {
            // || ($this->requestData->action === GridAction::LAYOUT && $request->getMethod() === Request::METHOD_POST)) {
            $responseBuilder->setStatus(Response::HTTP_CREATED);
        }

        return $responseBuilder;
    }

    /**
     * @throws QueryException
     */
    private function checkGridLayoutsTableExists(): bool
    {
        $exists = Schema::hasTable(Grid::LAYOUTS_TABLE);
        if (!$exists) {
            Log::warning('No ' . Grid::LAYOUTS_TABLE . ' table found');
        }

        return $exists;
    }

    /**
     * @throws QueryException
     * @throws \InvalidArgumentException
     */
    private function getUserLayouts(): Collection
    {
        if (!$this->checkGridLayoutsTableExists()) {
            return new Collection();
        }

        return DB::table(Grid::LAYOUTS_TABLE)->where(function ($query) {
            $user = Auth::user();
            if ($user) {
                $query->where('user_id', $user->id);
            }
            $query->orWhere('is_public', true);
        })->where('grid_name', $this->requestData->layout['grid_name'])->get();
    }

    /**
     * @return mixed
     *
     * @throws QueryException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws BadRequestException
     * @throws \Exception
     */
    private function createUserLayout()
    {
        if (!$this->checkGridLayoutsTableExists()) {
            throw new \BadMethodCallException("App doesn't support grid layouts");
        }
        $user = Auth::user();
        if (!$user) {
            throw new Exception('User must be logged in to update a grid layout');
        }

        $id = DB::table(Grid::LAYOUTS_TABLE)->insertGetId($this->requestData->layout);

        return DB::table(Grid::LAYOUTS_TABLE)->find($id);
    }

    /**
     * @throws QueryException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws BadRequestException
     * @throws \Exception
     */
    private function getLayoutWriteBuilder(): QueryBuilder
    {
        if (!$this->checkGridLayoutsTableExists()) {
            throw new \BadMethodCallException("App doesn't support grid layouts");
        }
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            throw new Exception('User must be logged in to update a grid layout');
        }

        return DB::table(Grid::LAYOUTS_TABLE)->where('id', $this->requestData->layout['id'])->where(function ($query) use ($user) {
            $query->where('user_id', $user->id);
            if ($user->isSuperAdmin()) {
                $query->orWhere('is_public', true);
            } else {
                $query->where('is_public', false);
            }
        });
    }

    /**
     * @return mixed
     *
     * @throws QueryException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws BadRequestException
     * @throws \Exception
     * @throws \RuntimeException
     */
    private function updateUserLayout()
    {
        $updated = $this->getLayoutWriteBuilder()->update($this->requestData->layout);
        if ($updated) {
            return DB::table(Grid::LAYOUTS_TABLE)->find($this->requestData->layout['id']);
        }

        throw new Exception('unable to update layout');
    }

    /**
     * @return mixed
     *
     * @throws \InvalidArgumentException
     * @throws QueryException
     * @throws \BadMethodCallException
     * @throws BadRequestException
     * @throws \Exception
     * @throws \RuntimeException
     */
    private function deleteUserLayout()
    {
        $old = DB::table(Grid::LAYOUTS_TABLE)->find($this->requestData->layout['id']);
        $deleted = $this->getLayoutWriteBuilder()->delete();
        if ($deleted) {
            return $old;
        }

        throw new Exception('unable to update layout');
    }

    /**
     * @return array
     */
    private function getEntityFilters(string $entity, array $filters)
    {
        return array_filter($filters, fn($c) => preg_match('/^' . preg_quote($entity, '/') . "\.[a-zA-Z0-9_]+$/", $c), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @psalm-param Collection<string, Field> $allFields
     *
     * @return void
     */
    private function addWhereFiltersIntoQuery(Builder|Relation $query, array $filters, Collection $allFields)
    {
        foreach ($filters as $filter) {
            $field = $allFields->offsetExists($filter['property']) ? $allFields->offsetGet($filter['property']) : false;
            if ($field && isset($filter['value'])) {
                $operator = FilterOperator::tryFrom($filter['operator']);
                if ($operator) {
                    static::applyCorrectWhereMethod($query, $field, $operator, $filter['value'] == 'null' ? null : $filter['value']);
                }
            }
        }
    }

    /**
     * get grid main data
     *
     * @return (Builder[]|\Illuminate\Database\Eloquent\Collection|int)[]
     *
     * @psalm-return list{\Illuminate\Database\Eloquent\Collection<array-key, Model>|array<Builder>, int}
     */
    private function getData(): array
    {
        $all_fields = $this->getAllFields()->filter(fn(Field $f) => !$f->isAppend());
        $request_columns = $this->requestData->columns;
        $main_columns = ($request_columns ? $this->getFields() : $all_fields)->map(fn(Field $f) => $f->getName())->toArray();
        $columns_filters = array_filter($this->requestData->filters ?? [], fn($f) => $f['value'] !== '');
        $funnels_filters = array_filter($this->requestData->funnelsFilters ?? [], fn($f) => $f['value'] !== []);
        $model_filters = [
            ...$this->getEntityFilters(lcfirst($this->getModelName()), $columns_filters),
            ...$this->getEntityFilters(lcfirst($this->getModelName()), $funnels_filters),
        ];

        $query = $this->getModel()::query();
        $query->select(array_values($main_columns) ?: ['*']);

        $this->addWhereFiltersIntoQuery($query, $model_filters, $all_fields);

        $all_relations = $this->getAllFullRelationsNames();
        foreach ($all_relations as $relation) {
            $relation_info = $this->getRelationDeeply($relation);
            if (!in_array($relation_info->info->getForeignKey(), $query->getQuery()->columns)) {
                $query->addSelect($relation_info->info->getForeignKey());
                // li aggiungo per fare la query ma mi salvo in qualche modo che poi non li devo vsualizzare perché non sono richiesti
                // $this->getModel()->makeHidden($relation_info->info->getForeignKey());
            }

            $relation_filters = [
                ...$this->getEntityFilters($relation, $columns_filters),
                ...$this->getEntityFilters($relation, $funnels_filters),
            ];

            $real_relation_name = preg_replace('/^' . lcfirst($this->getModelName()) . "\./", '', $relation);
            $query->with($real_relation_name, function ($q) use ($relation_filters, $relation_info, $all_fields) {
                $relation_columns = $relation_info->getFields()->map(fn($f) => $f->getName())->toArray();
                $q->select(!empty($relation_columns) ? array_values($relation_columns) : ['*']);
                if (!in_array($relation_info->info->getOwnerKey(), $q->getQuery()->getQuery()->columns)) {
                    $q->addSelect($relation_info->info->getOwnerKey());
                    // li aggiungo per fare la query ma mi salvo in qualche modo hce poi non li devo vsualizzare perché non sono richiesti
                    // $relation_info->getModel()->makeHidden($relation_info->info->getOwnerKey());
                }
                $this->addWhereFiltersIntoQuery($q, $relation_filters, $all_fields);
            });
            if (!empty($relation_filters)) {
                $query->whereHas($real_relation_name, function ($q) use ($relation_filters, $all_fields) {
                    $this->addWhereFiltersIntoQuery($q, $relation_filters, $all_fields);
                });
            }
        }

        // count is calculated before pagination but after filters
        $count = $query->count();

        if ($pagination = $this->requestData->pagination) {
            $query->skip((int) $pagination['from'] - 1);
            if (isset($pagination['to'])) {
                $query->take((int) $pagination['to'] - (int) $pagination['from'] + 1);
            }
        }

        if ($sorts = $this->requestData->sort) {
            $this->addSortsIntoQuery($query, $sorts);
        }

        // Log::debug(static::dumpQuery($query));
        $data = $query->get();

        return [$data, $count];
    }

    /**
     * @psalm-return Collection<string,\Modules\Core\Helpers\ResponseBuilder>|null
     */
    private function getFunnelsData(): ?Collection
    {
        $request_funnels = $this->requestData->funnelsFilters;
        if (!$request_funnels) {
            return null;
        }

        /** @psalm-var Collection<string,\Modules\Core\Helpers\ResponseBuilder> */
        $funnels = new Collection();
        /** @var string $name */
        foreach (array_keys($request_funnels) as $name) {
            $field = $this->getFieldDeeply($name);
            if ($funnel = $field->getFunnel()) {
                $response = $funnel->process($this->requestData);
                if ($response->isNotEmpty()) {
                    $funnels->offsetSet($name, $response->data());
                }
            }
        }

        return $funnels;
    }

    /**
     * @psalm-return Collection<string,\Modules\Core\Helpers\ResponseBuilder>|null
     */
    private function getOptionsData(): ?Collection
    {
        $request_options = $this->requestData->optionsFilters;
        if (!$request_options) {
            return null;
        }

        /** @psalm-var Collection<string,\Modules\Core\Helpers\ResponseBuilder> */
        $options = new Collection();
        /** @var string $name */
        foreach (array_keys($request_options) as $name) {
            // if (Str::startsWith($name, lcfirst($this->getModelName()))) $name = preg_replace("/^" . lcfirst($this->getModelName()) . "\./", '', $name);
            $field = $this->getFieldDeeply($name);
            if ($option = $field->getOption()) {
                $response = $option->process($this->requestData);
                if ($response->isNotEmpty()) {
                    $options->offsetSet($name, $response->data());
                }
            }
        }

        return $options;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function checkRecordsConcurrency(): ?Collection
    {
        $request_changes = $this->requestData->changes;
        if (!$request_changes) {
            return null;
        }

        $primary_key_fields = $this->getModel()->getKeyName();
        if (!is_array($primary_key_fields)) {
            $primary_key_fields = [$primary_key_fields];
        }
        $query = $this->getModel()::query();
        if ($this->hasSoftDelete()) {
            $query->withTrashed();
        }

        $fields = [];
        foreach ($request_changes as $record) {
            $query->orWhere(function ($q) use ($record, $primary_key_fields, &$fields) {
                if (!is_array($record['record'])) {
                    throw new \InvalidArgumentException('Invalid record value');
                }
                if ($primary_key_fields != array_keys($record['record'])) {
                    throw new \InvalidArgumentException('Invalid primary key fields');
                }

                static::applyCorrectWhereMethod($q, $record['property'], FilterOperator::NOT_EQUALS, $record['value']);
                foreach ($record['record'] as $column => $value) {
                    static::applyCorrectWhereMethod($q, $column, FilterOperator::EQUALS, $value);
                }

                if (empty($fields)) {
                    array_push($fields, ...array_keys($record['record']));
                    $fields[] = $record['property'];
                }
            });
        }

        $query->select(!empty($fields) ? $fields : ['*']);
        // Log::debug(static::dumpQuery($query));

        /** @psalm-suppress InvalidReturnStatement */
        return $query->get();
    }

    // private function getRecordFromPrimaryKey(): \Illuminate\Database\Eloquent\Collection
    // {
    // 	$query = $this->model::query();
    // 	if (is_array($full_primary_key)) {
    // 		$key_value = [];
    // 		$records_to_find = 0;
    // 		foreach ($full_primary_key as $full_pk) {
    // 			$key_value[$full_pk] = $this->getPrimaryKeyFromRequest($request, $full_pk, $writeType);
    // 			$records_to_find =  max($records_to_find, $this->putIntoWhere($this->getPrimaryKeyFromRequest($request, $full_pk, $writeType), $primary_key, $query));
    // 		}
    // 	} else {
    // 		$records_to_find = $this->putIntoWhere($this->getPrimaryKeyFromRequest($request, $full_primary_key, $writeType), $primary_key, $query);
    // 	}
    // 	if ($this->withTrashed && in_array(SoftDeletes::class, class_uses_recursive($query->getModel()::class))) $query->withTrashed();
    // 	/**
    // 	 *@var \Illuminate\Database\Eloquent\Collection
    // 	 */
    // 	$items_found = $query->sharedLock()->get();
    // 	$records_total = $items_found->count();
    // 	if ($records_total  != $records_to_find) {
    // 		Log::warning("Records to {$writeType->value} $records_to_find but found $records_total on recordset: " . json_encode($request->all()));
    // 		throw new \Exception("Records to {$writeType->value} " . $records_to_find . " but found " . $records_total);
    // 	}
    // 	return $items_found;
    // }

    private function createRecord(): bool
    {
        // DB::beginTransaction();
        $primary_key = $this->getPrimaryKey();
        $primary_value = is_string($primary_key) ? $this->requestData->request->get($primary_key) : array_map(fn($key) => $this->requestData->request->get($key), $primary_key);
        if ($this->getModel()->incrementing && $primary_value !== null && $primary_value != []) {
            throw new \UnexpectedValueException('Cannot assign a value to the primary key because is an autoincrement field');
        }
        $full_primary_key = $this->getFullPrimaryKey();
        $data = array_filter($this->requestData->request->all(), fn($k) => (is_array($full_primary_key) ? !in_array($k, $full_primary_key) : $k != $full_primary_key) && $k != 'action', ARRAY_FILTER_USE_KEY);
        // $validator = Validator::make($data, $this->getAllFields()->getRules);
        // $validated = $validator->stopOnFirstFailure(false)->validate();

        if ($callback = $this->onPreInsert()) {
            $data = $callback($data);
        }
        if ($data === false) {
            Log::info('Insert blocked by preInsert hook');

            return false;
        }

        if ($created = $this->getModel()->insert($data)) {
            // $records_total = $this->setModifiedData(collect([$created]), Response::HTTP_CREATED);
            // DB::commit();
        } else {
            // DB::rollBack();
            // $this->setNotModifiedData();
        }

        throw new Exception('Not implemented');
    }

    // private function getPrimaryKeyFromRequest(Request $request, string $key)
    // {
    // 	$key_value = [];
    // 	$primaryKey = $request->get('primaryKey');
    // 	if (isset($primaryKey) && is_array($primaryKey))
    // 		if (count($primaryKey) == 1) $key_value = $primaryKey[0][$key];
    // 		else
    // 			foreach ($primaryKey as $record) {
    // 				$key_value[] = $record[$key];
    // 			}
    // 	if (!$key_value || $key_value == []) {
    // 		$message = "Primary key '$key' is mandatory in " . $this->requestData->action . " requests";
    // 		Log::warning($message);
    // 		throw new \\UnexpectedValueException($message);
    // 	}
    // 	return $key_value;
    // }

    private function updateRecords()
    {
        $primary_key = $this->getPrimaryKey();
        $primary_value = is_string($primary_key) ? $this->requestData->request->get($primary_key) : array_map(fn($key) => $this->requestData->request->get($key), $primary_key);
        $query = $this->withTrashed() ? $this->getModel()::withTrashed() : $this->getModel()::query();
        $query->findOrFail($primary_value);

        throw new Exception('Not implemented');
    }

    private function softDeleteRecords()
    {
        throw new Exception('Not implemented');
    }

    private function forceDeleteRecords()
    {
        throw new Exception('Not implemented');
    }

    private function restoreRecords()
    {
        throw new Exception('Not implemented');
    }

    //endregion [EXECUTION]
}
