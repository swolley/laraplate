<?php

namespace Modules\Core\App\Http\Controllers;

use Closure;
use Throwable;
use BadMethodCallException;
use Doctrine\DBAL\Exception;
use Illuminate\Http\Request;
use UnexpectedValueException;
use Illuminate\Support\Carbon;
use Modules\Core\App\Models\User;
use Modules\Core\Crud\CrudHelper;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Cache\CacheManager;
use Approval\Traits\RequiresApproval;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\App\Models\Modification;
use Modules\Core\Locking\Traits\HasLocks;
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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Core\Locking\Exceptions\LockedModelException;
use Modules\Core\Locking\Exceptions\CannotUnlockException;
use Modules\Core\Locking\Exceptions\AlreadyLockedException;
use Illuminate\Contracts\Container\BindingResolutionException;
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
            return $operation($response_builder, $filters);
        } catch (QueryException $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
                ->getResponse();
        } catch (LockedModelException $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_LOCKED)->getResponse();
        } catch (UnexpectedValueException | BadMethodCallException $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_BAD_REQUEST)->getResponse();
        } catch (\LogicException | AlreadyLockedException | CannotUnlockException $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_NOT_MODIFIED)->getResponse();
        } catch (ModelNotFoundException $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_NO_CONTENT)->getResponse();
        } catch (UnauthorizedException $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_UNAUTHORIZED)->getResponse();
        } catch (Throwable $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_INTERNAL_SERVER_ERROR)->getResponse();
        }
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
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, ListRequestData $filters): Response {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return CacheManager::tryByRequest($model, $filters->request, function () use ($model, $filters, $response_builder) {
                $query = $model::query();
                $crud_helper = new CrudHelper();
                $crud_helper->prepareQuery($query, $filters);

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
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, DetailRequestData $filters): Response {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return CacheManager::tryByRequest($model, $filters->request, function () use ($model, $filters, $response_builder) {
                $query = $model::query();
                $crud_helper = new CrudHelper();
                $crud_helper->prepareQuery($query, $filters);

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
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, HistoryRequestData $filters): Response {
            $model = $filters->model;
            if (!$this->hasHistory($model)) {
                throw new BadMethodCallException("'$filters->mainEntity' doesn't have history handling");
            }
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return CacheManager::tryByRequest($model, $filters->request, function () use ($model, $filters, $response_builder) {
                $query = $model::query();
                $crud_helper = new CrudHelper();
                $crud_helper->prepareQuery($query, $filters);
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
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, TreeRequestData $filters): Response {
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
                $crud_helper = new CrudHelper();
                $crud_helper->prepareQuery($query, $filters);

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
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, ModifyRequestData $values, $request): Response {
            $model = $values->model;
            PermissionChecker::ensurePermissions($values->request, $model->getTable(), 'insert', $model->getConnectionName());
            // $values = method_exists($model, 'getRules') ? $request->validate($model->getRules()) : $filters;
            // $values = $request->all();
            // se ci sono proprietà che non sono nei fillable devo restituire errore?
            $discarded_values = $this->removeNotFillableProperties($model, $values->changes);

            $created = $model->create($values->changes);
            if (!$created) {
                throw new \LogicException('Record not created');
            }

            $created->fresh();

            return $response_builder
                ->setData($created)
                ->setStatus(Response::HTTP_CREATED)
                ->setError(!empty($discarded_values) ? $discarded_values : null)
                ->getResponse();
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
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, ModifyRequestData $values): Response {
            $model = $values->model;
            PermissionChecker::ensurePermissions($values->request, $model->getTable(), 'update', $model->getConnectionName());
            // if $filters->request->method() == 'PUT' devo sovrascrivere tutto il record quindi devono esserci tutti i fillable e devo fare le validazioni
            // else valido quello che ho con le regole che ho se le ho
            if ($model->usesTimestamps() && !isset($values->{$model::UPDATED_AT})) {
                throw new BadMethodCallException($model::UPDATED_AT . ' field is required when updating an entity that uses timestamps');
            }
            $key_value = $this->getModelKeyValue($values);
            $found_records = $model->where($key_value)->get();
            // se ci sono proprietà che non sono nei fillable devo restituire errore?
            $discarded_values = $this->removeNonFillableProperties($model, $values->changes);

            // TODO: come gestire la preview del record? E se ci sono modifiche in pending cosa devo fare?
            // 1) impedisco la modifica finché non è approvato/disapprovato tutto
            // 2) posso mettere un flag "force" che disapprova le modifiche in pending e ne crea una nuova?


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

            return $response_builder
                ->setData($updated_records)
                ->getResponse();
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
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, ModifyRequestData $filters): Response {
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

            return $response_builder
                ->setData($deleted_records)
                ->getResponse();
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
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, ModifyRequestData $filters) use ($operation): Response {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'restore', $model->getConnectionName());
            $key = $filters->primaryKey;
            $key_value = is_string($key) ? $filters->$key : array_map(fn($k) => $filters->$k, $key);
            $found_record = $model->withTrashed()->findOrFail($key_value);
            if ($operation === 'activate' && !$found_record->restore()) {
                throw new \LogicException('Record not activated');
            } elseif (!$found_record->delete()) {
                throw new \LogicException('Record not inactivated');
            }

            $found_record->fresh();

            return $response_builder
                ->setData($found_record)
                ->getResponse();
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
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, ModifyRequestData $filters) use ($operation): Response {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'approve', $model->getConnectionName());
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

            return $response_builder
                ->setData($found_record)
                ->getResponse();
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
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, ModifyRequestData $filters) use ($operation): Response {
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

            return $response_builder
                ->setData($locked_records)
                ->getResponse();
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
        return $this->executeOperation($request, function (ResponseBuilder $response_builder, CrudRequestData $filters): Response {
            $model = $filters->model;
            $table = $model->getTable();
            Cache::tags([config('app.name'), $table])->flush();

            return $response_builder
                ->setData("$table cached cleared")
                ->setStatus(Response::HTTP_OK)
                ->getResponse();
        });
    }

    //endregion
}
