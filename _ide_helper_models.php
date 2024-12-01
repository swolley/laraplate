<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperApproval
 * @property int $id
 * @property int $modification_id
 * @property int $approver_id
 * @property string $approver_type
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $approver
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Approval\Models\Modification $modification
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Approval newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Approval newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Approval query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Approval whereApproverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Approval whereApproverType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Approval whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Approval whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Approval whereModificationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Approval whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Approval whereUpdatedAt($value)
 */
	class Approval extends \Eloquent {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperCronJob
 * @property int $id
 * @property string $name
 * @property string $command
 * @property array $parameters
 * @property \Cron\CronExpression $schedule
 * @property bool $is_active
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Modules\Core\Database\Factories\CronJobFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob lockedBy(\Modules\Core\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob unlockedBy(\Modules\Core\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereCommand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereParameters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereSchedule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob withoutTrashed()
 */
	class CronJob extends \Eloquent {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperDisapproval
 * @property int $id
 * @property int $modification_id
 * @property int $disapprover_id
 * @property string $disapprover_type
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $approver
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Approval\Models\Modification $modification
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Disapproval newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Disapproval newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Disapproval query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Disapproval whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Disapproval whereDisapproverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Disapproval whereDisapproverType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Disapproval whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Disapproval whereModificationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Disapproval whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Disapproval whereUpdatedAt($value)
 */
	class Disapproval extends \Eloquent {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperDynamicEntity
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\DynamicEntity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\DynamicEntity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\DynamicEntity onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\DynamicEntity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\DynamicEntity withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\DynamicEntity withoutTrashed()
 */
	final class DynamicEntity extends \Eloquent {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperLicense
 * @property string $id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_to
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Modules\Core\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License expiredAt(\Illuminate\Support\Carbon $date)
 * @method static \Modules\Core\Database\Factories\LicenseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License free()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License occupied()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License validAt(\Illuminate\Support\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License whereValidTo($value)
 */
	class License extends \Eloquent {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperModelEmbedding
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property array $embedding
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $model
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding whereEmbedding($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding whereUpdatedAt($value)
 */
	class ModelEmbedding extends \Eloquent {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperModification
 * @property int $id
 * @property int|null $modifiable_id
 * @property string|null $modifiable_type
 * @property int|null $modifier_id
 * @property string|null $modifier_type
 * @property bool $active
 * @property bool $is_update
 * @property int $approvers_required
 * @property int $disapprovers_required
 * @property string $md5
 * @property array $modifications
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Approval> $approvals
 * @property-read int|null $approvals_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Disapproval> $disapprovals
 * @property-read int|null $disapprovals_count
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read int $approvals_remaining
 * @property-read int $approvers_remaining
 * @property-read int $disapprovals_remaining
 * @property-read int $disapprovers_remaining
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $modifiable
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $modifier
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification activeOnly()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification changes()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification creations()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification inactiveOnly()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereApproversRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereDisapproversRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereIsUpdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereMd5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereModifiableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereModifiableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereModifications($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereModifierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereModifierType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Modification whereUpdatedAt($value)
 */
	class Modification extends \Eloquent {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperPermission
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $connection_name
 * @property string|null $table_name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Casts\ActionEnum|null $action
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereConnectionName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereGuardName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereTableName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission withoutTrashed()
 */
	class Permission extends \Eloquent {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperRole
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $locked_at
 * @property string|null $locked_user_id
 * @property int|null $parent_id
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\Models\Role[] $children
 * @property-read int|null $children_count
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Modules\Core\Models\Role|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\Models\Role[] $ancestors The model's recursive parents.
 * @property-read int|null $ancestors_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\Models\Role[] $ancestorsAndSelf The model's recursive parents and itself.
 * @property-read int|null $ancestors_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\Models\Role[] $bloodline The model's ancestors, descendants and itself.
 * @property-read int|null $bloodline_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\Models\Role[] $childrenAndSelf The model's direct children and itself.
 * @property-read int|null $children_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\Models\Role[] $descendants The model's recursive children.
 * @property-read int|null $descendants_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\Models\Role[] $descendantsAndSelf The model's recursive children and itself.
 * @property-read int|null $descendants_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\Models\Role[] $parentAndSelf The model's direct parent and itself.
 * @property-read int|null $parent_and_self_count
 * @property-read \Modules\Core\Models\Role|null $rootAncestor The model's topmost parent.
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\Models\Role[] $siblings The parent's other children.
 * @property-read int|null $siblings_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\Models\Role[] $siblingsAndSelf All the parent's children.
 * @property-read int|null $siblings_and_self_count
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, static> all($columns = ['*'])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role breadthFirst()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role depthFirst()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role doesntHaveChildren()
 * @method static \Modules\Core\Database\Factories\RoleFactory factory($count = null, $state = [])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, static> get($columns = ['*'])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role getExpressionGrammar()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role hasChildren()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role hasParent()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role isLeaf()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role isRoot()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role locked()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role lockedBy(\Modules\Core\Models\User $user)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role newModelQuery()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Role onlyTrashed()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role permission($permissions, $without = false)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role query()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role tree($maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role treeOf(\Illuminate\Database\Eloquent\Model|callable $constraint, $maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role unlocked()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role unlockedBy(\Modules\Core\Models\User $user)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereCreatedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereDeletedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereDepth($operator, $value = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereDescription($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereGuardName($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereLockedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereLockedUserId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereName($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereParentId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereUpdatedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role withGlobalScopes(array $scopes)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role withRelationshipExpression($direction, callable $constraint, $initialDepth, $from = null, $maxDepth = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Role withTrashed()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Role withoutTrashed()
 */
	class Role extends \Eloquent {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperSetting
 * @property int $id
 * @property string $name
 * @property array $value
 * @property bool $encrypted
 * @property array|null $choices
 * @property \Modules\Core\Casts\SettingTypeEnum $type
 * @property string $group_name
 * @property string $description
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read array|null $preview
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Modification> $modifications
 * @property-read int|null $modifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Modules\Core\Database\Factories\SettingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereChoices($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereEncrypted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereGroupName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting withoutTrashed()
 */
	class Setting extends \Eloquent {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperUser
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $username
 * @property string|null $lang
 * @property string|null $last_login_at
 * @property string|null $license_id
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $locked_at
 * @property string|null $locked_user_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $two_factor_confirmed_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Approval> $approvals
 * @property-read int|null $approvals_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Permission> $defaultPermissions
 * @property-read int|null $default_permissions_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $defaultRoles
 * @property-read int|null $default_roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Disapproval> $disapprovals
 * @property-read int|null $disapprovals_count
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\UserGridConfig> $grid_configs
 * @property-read int|null $grid_configs_count
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Modules\Core\Models\License|null $license
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Modules\Core\Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User lockedBy(\Modules\Core\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User unlockedBy(\Modules\Core\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereLang($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereLicenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereLockedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User withoutTrashed()
 */
	class User extends \Eloquent {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperUserGridConfig
 * @property int $id
 * @property int|null $user_id
 * @property string $grid_name
 * @property string $layout_name
 * @property bool $is_public
 * @property array $config
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Modules\Core\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\UserGridConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\UserGridConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\UserGridConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\UserGridConfig whereConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\UserGridConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\UserGridConfig whereGridName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\UserGridConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\UserGridConfig whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\UserGridConfig whereLayoutName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\UserGridConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\UserGridConfig whereUserId($value)
 */
	class UserGridConfig extends \Eloquent {}
}

namespace Modules\Editorial\Models{
/**
 * 
 *
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Modules\Core\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Author newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Author newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Author onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Author query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Author withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Author withoutTrashed()
 */
	class Author extends \Eloquent {}
}

namespace Modules\Editorial\Models{
/**
 * 
 *
 * @property int $id
 * @property string $entity
 * @property int $newspaper_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $model_type_id
 * @property int $order
 * @property int $persistence
 * @property string|null $logo
 * @property string|null $logo_full
 * @property bool $is_active
 * @property int|null $template_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Editorial\Models\Folder[] $children
 * @property-read int|null $children_count
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Modules\Editorial\Models\Newspaper $newspaper
 * @property-read \Modules\Editorial\Models\Folder|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Editorial\Models\Folder[] $ancestors The model's recursive parents.
 * @property-read int|null $ancestors_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Editorial\Models\Folder[] $ancestorsAndSelf The model's recursive parents and itself.
 * @property-read int|null $ancestors_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Editorial\Models\Folder[] $bloodline The model's ancestors, descendants and itself.
 * @property-read int|null $bloodline_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Editorial\Models\Folder[] $childrenAndSelf The model's direct children and itself.
 * @property-read int|null $children_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Editorial\Models\Folder[] $descendants The model's recursive children.
 * @property-read int|null $descendants_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Editorial\Models\Folder[] $descendantsAndSelf The model's recursive children and itself.
 * @property-read int|null $descendants_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Editorial\Models\Folder[] $parentAndSelf The model's direct parent and itself.
 * @property-read int|null $parent_and_self_count
 * @property-read \Modules\Editorial\Models\Folder|null $rootAncestor The model's topmost parent.
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Editorial\Models\Folder[] $siblings The parent's other children.
 * @property-read int|null $siblings_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Editorial\Models\Folder[] $siblingsAndSelf All the parent's children.
 * @property-read int|null $siblings_and_self_count
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, static> all($columns = ['*'])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder breadthFirst()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder depthFirst()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder doesntHaveChildren()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, static> get($columns = ['*'])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder getExpressionGrammar()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder hasChildren()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder hasParent()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder isLeaf()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder isRoot()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder newModelQuery()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder onlyTrashed()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder query()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder tree($maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder treeOf(\Illuminate\Database\Eloquent\Model|callable $constraint, $maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereCreatedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereDeletedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereDepth($operator, $value = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereDescription($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereEntity($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereIsActive($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereLogo($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereLogoFull($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereModelTypeId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereName($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereNewspaperId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereOrder($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereParentId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder wherePersistence($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereSlug($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereTemplateId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder whereUpdatedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder withGlobalScopes(array $scopes)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder withRelationshipExpression($direction, callable $constraint, $initialDepth, $from = null, $maxDepth = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Folder withoutTrashed()
 */
	class Folder extends \Eloquent {}
}

namespace Modules\Editorial\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $newspaper_id
 * @property string $entity
 * @property string $name
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Modules\Editorial\Models\Newspaper|null $newspaper
 * @property-read \Modules\Editorial\Models\Template|null $template
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType whereEntity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType whereNewspaperId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\ModelType withoutTrashed()
 */
	class ModelType extends \Eloquent {}
}

namespace Modules\Editorial\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $primary_color
 * @property string $secondary_color
 * @property string $logo
 * @property string $logo_full
 * @property string $domain
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Editorial\Models\Folder> $categories
 * @property-read int|null $categories_count
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Editorial\Models\ModelType> $models
 * @property-read int|null $models_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Editorial\Models\Folder> $sections
 * @property-read int|null $sections_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper whereDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper whereLogoFull($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper wherePrimaryColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper whereSecondaryColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Newspaper withoutTrashed()
 */
	class Newspaper extends \Eloquent {}
}

namespace Modules\Editorial\Models{
/**
 * 
 *
 * @property int $id
 * @property array $name
 * @property array $slug
 * @property string|null $type
 * @property int|null $order_column
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read mixed $translations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag containing(string $name, $locale = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Tag withType(?string $type = null)
 */
	class Tag extends \Eloquent {}
}

namespace Modules\Editorial\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $newspaper_id
 * @property string $name
 * @property string $content
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Editorial\Models\ModelType> $models
 * @property-read int|null $models_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Template newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Template newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Template query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Template whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Template whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Template whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Template whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Template whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Template whereNewspaperId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Editorial\Models\Template whereUpdatedAt($value)
 */
	class Template extends \Eloquent {}
}

