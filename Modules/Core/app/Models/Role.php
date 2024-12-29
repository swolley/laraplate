<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Modules\Core\Cache\HasCache;
use Illuminate\Support\Collection;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Models\Pivot\ModelHasRole;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as BaseRole;
use Modules\Core\Database\Factories\RoleFactory;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * @mixin IdeHelperRole
 */
class Role extends BaseRole
{
    use HasFactory, HasLocks, HasRecursiveRelationships, HasValidations, SoftDeletes, HasCache, HasVersions {
        getRules as protected getRulesTrait;
    }

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

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }

    #[\Override]
    public function users(): BelongsToMany
    {
        return parent::users()->using(ModelHasRole::class);
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

    public function getRules()
    {
        $rules = $this->getRulesTrait();
        $rules[static::DEFAULT_RULE] = array_merge($rules[static::DEFAULT_RULE], [
            'guard_name' => ['string', 'max:255'],
            'description' => ['string', 'max:255', 'nullable'],
            'locked_at' => ['date', 'nullable'],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,' . $this->id],
        ]);
        return $rules;
    }
}
