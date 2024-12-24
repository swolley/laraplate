<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Casts\CrudExecutor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\UnauthorizedException;

/**
 * @phpstan-type HasValidationsType HasValidations
 */
trait HasValidations
{
    public const DEFAULT_RULE = 'always';

    private $rules = [
        'create' => [],
        'update' => [],
        // 'always' => [],
    ];

    protected static function bootHasValidations(): void
    {
        // non esiste un evento prima del retrieved, purtroppo in questo caso faccio la query e poi controllo se l'utente può leggere
        static::retrieved(function (Model $model): void {
            if (!static::checkUserCanDo($model, 'select')) {
                throw new UnauthorizedException('User cannot select ' . $model->getTable());
            }
        });
        // static::addGlobalScope('selectPermissions', function (Builder $query) {
        // 	if (!static::checkUserCanDo($query->getModel(), 'select')) throw new \Junges\ACL\Exceptions\UnauthorizedException('User cannot select ' . $query->getTable());
        // });
        static::creating(function (Model $model): void {
            if (!static::checkUserCanDo($model, 'insert')) {
                throw new UnauthorizedException('User cannot insert ' . $model->getTable());
            }
            $model->validateWithRules(CrudExecutor::INSERT);
        });
        static::updating(function (Model $model): void {
            if (!static::checkUserCanDo($model, 'update')) {
                throw new UnauthorizedException('User cannot update ' . $model->getTable());
            }

            if (!$model->isDirty('deleted_at')) {
                $model->validateWithRules(CrudExecutor::UPDATE);
            }
        });
        static::deleting(function (Model $model): void {
            if ((!method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) && !static::checkUserCanDo($model, 'forceDelete')) {
                throw new UnauthorizedException('User cannot delete ' . $model->getTable());
            }

            if (!static::checkUserCanDo($model, 'delete')) {
                throw new UnauthorizedException('User cannot soft delete ' . $model->getTable());
            }
        });

        if (method_exists(static::class, 'forceDeleting')) {
            static::forceDeleting(function (Model $model): void {
                if (!static::checkUserCanDo($model, 'forceDelete')) {
                    throw new UnauthorizedException('User cannot delete ' . $model->getTable());
                }
            });
        }

        if (method_exists(static::class, 'restoring')) {
            static::restoring(function (Model $model): void {
                if (!static::checkUserCanDo($model, 'restore')) {
                    throw new UnauthorizedException('User cannot restore ' . $model->getTable());
                }
            });
        }
    }

    protected static function checkUserCanDo(Model $model, string $operation): bool
    {
        $permission = $model->getTable() . '.' . $operation;
        $permission_class = config('permission.models.permission');

        if (!$permission_class::whereName($permission)->count()) {
            return true;
        }

        if ($user = Auth::user()) {
            /** @var User $user */
            return $user->isSuperAdmin() || $user->hasPermission($permission);
        }

        return true;
    }

    public function getRules(): array
    {
        $primary_key = $this->getKeyName();
        $rules = $this->rules;
        if (!isset($rules[static::DEFAULT_RULE])) {
            $rules[static::DEFAULT_RULE] = [];
        }
        $rules['update'] = array_merge($rules['update'], [
            $primary_key => 'required|exists:' . $this->getTable() . ',' . $primary_key,
        ]);
        return $rules;
    }

    public function getOperationRules(?string $operation = null): array
    {
        $rules = $this->getRules();
        return $operation && array_key_exists($operation, $rules) ? array_merge($rules[static::DEFAULT_RULE] ?? [], $rules[$operation]) : $rules[static::DEFAULT_RULE] ?? [];
    }

    public function validateWithRules(string $operation): void
    {
        $rules = $this->getOperationRules($operation);

        if (!empty($rules)) {
            Validator::make($this->getAttributes(), $rules)->validate();
        }
    }
}
