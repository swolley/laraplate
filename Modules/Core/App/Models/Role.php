<?php

declare(strict_types=1);

namespace Modules\Core\App\Models;

use Illuminate\Support\Collection;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\App\Helpers\HasValidations;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as BaseRole;
use Modules\Core\App\Helpers\HasCommonObserver;
use Modules\Core\Database\Factories\RoleFactory;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class Role extends BaseRole
{
    use HasCommonObserver, HasFactory, HasLocks, HasRecursiveRelationships, HasValidations, SoftDeletes;

    /**
     * @var string[]
     *
     * @psalm-suppress NonInvariantPropertyType
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $fillable = [
        'name',
        'guard_name',
        'description',
    ];

    /**
     * @var string[]
     *
     * @psalm-suppress NonInvariantPropertyType
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $hidden = [
        'parent_id',
        'pivot',
    ];

    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->rules[static::DEFAULT_RULE] = [
            'name' => 'string|required|max:255',
            'guard_name' => 'string|max:255',
            'description' => 'string|max:255|nullable',
            'locked_at' => 'date|nullable',
        ];
    }

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }

    /**
     *
     */
    public function getAllPermissions(): Collection
    {
        /** @psalm-suppress UndefinedThisPropertyFetch */
        $permissions = $this->permissions;

        /**
         * @psalm-suppress UndefinedThisPropertyFetch
         *
         * @var Role $parent
         */
        foreach ($this->ancestors as $parent) {
            $permissions = $permissions->merge($parent->permissions);
        }

        return $permissions->sort()->values();
    }

    /**
     * @throws PermissionDoesNotExist
     * @throws GuardDoesNotMatch
     */
    public function hasPermission(string $permission): bool
    {
        $has_permission = parent::hasPermissionTo($permission);

        if ($has_permission) {
            return true;
        }

        /**
         * @psalm-suppress UndefinedThisPropertyFetch
         *
         * @var Role $parent
         */
        foreach ($this->ancestors as $parent) {
            if ($parent->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
