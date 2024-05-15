<?php

namespace Modules\Core\App\Http\Controllers;

use Exception;
use Throwable;
use Illuminate\Http\Request;
use UnexpectedValueException;
use Exception as GlobalException;
use Illuminate\Routing\Controller;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Components\Grid;
use Modules\Core\Grids\Casts\GridAction;
use Modules\Core\App\Models\DynamicEntity;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Grids\Requests\GridRequest;
use Doctrine\DBAL\Exception as DBALException;
use Modules\Core\App\Helpers\ResponseBuilder;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Modules\Core\App\Helpers\PermissionChecker;
use Illuminate\Validation\UnauthorizedException;
use PHPUnit\Framework\ExpectationFailedException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

class GridsController extends Controller
{
    private function findOperation(GridRequest $request): string
    {
        switch ($request->get('action')) {
            case GridAction::DELETE:
                return 'delete';
            case GridAction::RESTORE:
                return 'restore';
            case GridAction::INSERT:
                return 'insert';
                // TODO: manca la gestione della preview nelle griglie
                // case GridAction::APPROVE:
                //     return 'approve';
                // case GridAction::DIAPPROVE:
                //     return 'diapprove';
        }

        return match (strtolower($request->getMethod())) {
            'get' => 'select',
            'post' => 'insert',
            'put', 'patch' => 'update',
            'delete' => 'forceDelete',
        };
    }

    /**
     * get all grid models configurations
     *
     * @return Response
     *
     * @throws DirectoryNotFoundException
     * @throws BindingResolutionException
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws GlobalException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function getGridsConfigs(Request $request, ?string $entity = null)
    {
        try {
            $grids = [];
            $permissions = $request->user()->getAllPermissions();

            if ($entity) {
                $entity_instance = DynamicEntity::tryResolveModel($entity);
                if (!$entity_instance) {
                    throw new UnexpectedValueException("Unable to find entity '$entity'");
                }
                $entity = $entity_instance;
            }

            foreach (models() as $model) {
                /** @var Model */
                $instance = new $model;
                $table = $instance->getTable();
                if ((!$entity || $instance::class === $entity::class) && Grid::useGridUtils($instance) && PermissionChecker::ensurePermissions($request, $table, connection: $instance->getConnectionName(), permissions: $permissions)) {
                    /** @var Model&HasGridUtils $instance */
                    /** @var Grid $grid */
                    $grid = $instance->getGrid();
                    $grids[$table] = $grid->getConfigs();
                }
            }

            if ($entity) {
                if (empty($grids)) {
                    throw new UnexpectedValueException("'$entity' is not a Grid");
                }
                $grids = head($grids);
            }

            $response_builder = (new ResponseBuilder($request))->setData($grids);
        } catch (UnexpectedValueException $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_BAD_REQUEST);
        } catch (UnauthorizedException $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_UNAUTHORIZED);
        } finally {
            return $response_builder->json();
        }
    }

    /**
     * @throws UnexpectedValueException
     * @throws BindingResolutionException
     * @throws ValidationException
     * @throws DBALException
     * @throws UnauthorizedException
     */
    public function grid(GridRequest $request, string $entity): Response
    {
        try {
            $filters = $request->validated();
            $model = DynamicEntity::resolve($entity, $filters['connection'] ?? null, request: $request);
            if (!Grid::useGridUtils($model)) {
                // throw new UnexpectedValueException("'$entity' is not a Grid");
                // TODO: da verificare, solo imbastito
                $class = $model::class;
                $model = eval(<<<PHP_EOL
                    new class extends $class {
                        use HasGridUtils;
                    }
                PHP_EOL);
            }
            PermissionChecker::ensurePermissions($request, $model->getTable(), $this->findOperation($request), $model->getConnectionName());

            return $model->getGrid()->process($request);
        } catch (UnexpectedValueException | UnauthorizedException $ex) {
            return (new ResponseBuilder($request))
                ->setData($ex)
                ->json();
        }
    }
}
