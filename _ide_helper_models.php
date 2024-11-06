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


namespace Modules\Core\App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $modification_id
 * @property int $approver_id
 * @property string $approver_type
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $approver
 * @property-read \Overtrue\LaravelVersionable\Version|null $firstVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $lastVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $latestVersion
 * @property-read \Approval\Models\Modification $modification
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Overtrue\LaravelVersionable\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Approval newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Approval newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Approval query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Approval whereApproverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Approval whereApproverType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Approval whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Approval whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Approval whereModificationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Approval whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Approval whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperApproval {}
}

namespace Modules\Core\App\Models{
/**
 * 
 *
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
 * @property-read \Overtrue\LaravelVersionable\Version|null $firstVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $lastVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Overtrue\LaravelVersionable\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Modules\Core\Database\Factories\CronJobFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob lockedBy(\Modules\Core\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob unlockedBy(\Modules\Core\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob whereCommand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob whereParameters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob whereSchedule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\CronJob withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCronJob {}
}

namespace Modules\Core\App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $modification_id
 * @property int $disapprover_id
 * @property string $disapprover_type
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $approver
 * @property-read \Overtrue\LaravelVersionable\Version|null $firstVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $lastVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $latestVersion
 * @property-read \Approval\Models\Modification $modification
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Overtrue\LaravelVersionable\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Disapproval newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Disapproval newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Disapproval query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Disapproval whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Disapproval whereDisapproverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Disapproval whereDisapproverType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Disapproval whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Disapproval whereModificationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Disapproval whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Disapproval whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperDisapproval {}
}

namespace Modules\Core\App\Models{
/**
 * 
 *
 * @property-read \Overtrue\LaravelVersionable\Version|null $firstVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $lastVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Overtrue\LaravelVersionable\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\DynamicEntity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\DynamicEntity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\DynamicEntity onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\DynamicEntity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\DynamicEntity withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\DynamicEntity withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperDynamicEntity {}
}

namespace Modules\Core\App\Models{
/**
 * 
 *
 * @property string $id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_to
 * @property-read \Overtrue\LaravelVersionable\Version|null $firstVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $lastVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $latestVersion
 * @property-read \Modules\Core\App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Overtrue\LaravelVersionable\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License expiredAt(\Illuminate\Support\Carbon $date)
 * @method static \Modules\Core\Database\Factories\LicenseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License free()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License occupied()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License validAt(\Illuminate\Support\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\License whereValidTo($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperLicense {}
}

namespace Modules\Core\App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property array $embedding
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $model
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\ModelEmbedding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\ModelEmbedding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\ModelEmbedding query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\ModelEmbedding whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\ModelEmbedding whereEmbedding($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\ModelEmbedding whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\ModelEmbedding whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\ModelEmbedding whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\ModelEmbedding whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperModelEmbedding {}
}

namespace Modules\Core\App\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $modifiable_id
 * @property string|null $modifiable_type
 * @property int|null $modifier_id
 * @property string|null $modifier_type
 * @property int $active
 * @property int $is_update
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
 * @property-read \Overtrue\LaravelVersionable\Version|null $firstVersion
 * @property-read int $approvals_remaining
 * @property-read int $approvers_remaining
 * @property-read int $disapprovals_remaining
 * @property-read int $disapprovers_remaining
 * @property-read \Overtrue\LaravelVersionable\Version|null $lastVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $modifiable
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $modifier
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Overtrue\LaravelVersionable\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification activeOnly()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification changes()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification creations()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification inactiveOnly()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereApproversRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereDisapproversRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereIsUpdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereMd5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereModifiableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereModifiableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereModifications($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereModifierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereModifierType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Modification whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperModification {}
}

namespace Modules\Core\App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $connection_name
 * @property string|null $table_name
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Overtrue\LaravelVersionable\Version|null $firstVersion
 * @property-read \Modules\Core\App\Casts\ActionEnum|null $action
 * @property-read \Overtrue\LaravelVersionable\Version|null $lastVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Overtrue\LaravelVersionable\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission whereConnectionName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission whereGuardName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission whereTableName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Permission withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPermission {}
}

namespace Modules\Core\App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $locked_at
 * @property string|null $locked_user_id
 * @property int|null $parent_id
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\App\Models\Role[] $children
 * @property-read int|null $children_count
 * @property-read \Overtrue\LaravelVersionable\Version|null $firstVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $lastVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $latestVersion
 * @property-read \Modules\Core\App\Models\Role|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Overtrue\LaravelVersionable\Version> $versions
 * @property-read int|null $versions_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\App\Models\Role[] $ancestors The model's recursive parents.
 * @property-read int|null $ancestors_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\App\Models\Role[] $ancestorsAndSelf The model's recursive parents and itself.
 * @property-read int|null $ancestors_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\App\Models\Role[] $bloodline The model's ancestors, descendants and itself.
 * @property-read int|null $bloodline_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\App\Models\Role[] $childrenAndSelf The model's direct children and itself.
 * @property-read int|null $children_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\App\Models\Role[] $descendants The model's recursive children.
 * @property-read int|null $descendants_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\App\Models\Role[] $descendantsAndSelf The model's recursive children and itself.
 * @property-read int|null $descendants_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\App\Models\Role[] $parentAndSelf The model's direct parent and itself.
 * @property-read int|null $parent_and_self_count
 * @property-read \Modules\Core\App\Models\Role|null $rootAncestor The model's topmost parent.
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\App\Models\Role[] $siblings The parent's other children.
 * @property-read int|null $siblings_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection|\Modules\Core\App\Models\Role[] $siblingsAndSelf All the parent's children.
 * @property-read int|null $siblings_and_self_count
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, static> all($columns = ['*'])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role breadthFirst()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role depthFirst()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role doesntHaveChildren()
 * @method static \Modules\Core\Database\Factories\RoleFactory factory($count = null, $state = [])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, static> get($columns = ['*'])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role getExpressionGrammar()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role hasChildren()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role hasParent()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role isLeaf()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role isRoot()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role locked()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role lockedBy(\Modules\Core\App\Models\User $user)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role newModelQuery()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Role onlyTrashed()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role permission($permissions, $without = false)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role query()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role tree($maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role treeOf(\Illuminate\Database\Eloquent\Model|callable $constraint, $maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role unlocked()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role unlockedBy(\Modules\Core\App\Models\User $user)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role whereCreatedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role whereDeletedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role whereDepth($operator, $value = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role whereDescription($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role whereGuardName($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role whereId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role whereLockedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role whereLockedUserId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role whereName($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role whereParentId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role whereUpdatedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role withGlobalScopes(array $scopes)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role withRelationshipExpression($direction, callable $constraint, $initialDepth, $from = null, $maxDepth = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Role withTrashed()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\App\Models\Role withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Role withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperRole {}
}

namespace Modules\Core\App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property array $value
 * @property bool $encrypted
 * @property array|null $choices
 * @property \Modules\Core\App\Casts\SettingTypeEnum $type
 * @property string $group_name
 * @property string $description
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Overtrue\LaravelVersionable\Version|null $firstVersion
 * @property-read array|null $preview
 * @property-read \Overtrue\LaravelVersionable\Version|null $lastVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Modification> $modifications
 * @property-read int|null $modifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Overtrue\LaravelVersionable\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Modules\Core\Database\Factories\SettingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting whereChoices($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting whereEncrypted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting whereGroupName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting whereValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\Setting withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSetting {}
}

namespace Modules\Core\App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $two_factor_confirmed_at
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Approval> $approvals
 * @property-read int|null $approvals_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $defaultPermissions
 * @property-read int|null $default_permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $defaultRoles
 * @property-read int|null $default_roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Disapproval> $disapprovals
 * @property-read int|null $disapprovals_count
 * @property-read \Overtrue\LaravelVersionable\Version|null $firstVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\App\Models\UserGridConfig> $grid_configs
 * @property-read int|null $grid_configs_count
 * @property-read \Overtrue\LaravelVersionable\Version|null $lastVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $latestVersion
 * @property-read \Modules\Core\App\Models\License|null $license
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Overtrue\LaravelVersionable\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Modules\Core\Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User lockedBy(\Modules\Core\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User unlockedBy(\Modules\Core\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereLang($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereLicenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereLockedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\User withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

namespace Modules\Core\App\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $grid_name
 * @property string $layout_name
 * @property bool $is_public
 * @property array $config
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Overtrue\LaravelVersionable\Version|null $firstVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $lastVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $latestVersion
 * @property-read \Modules\Core\App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Overtrue\LaravelVersionable\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\UserGridConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\UserGridConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\UserGridConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\UserGridConfig whereConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\UserGridConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\UserGridConfig whereGridName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\UserGridConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\UserGridConfig whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\UserGridConfig whereLayoutName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\UserGridConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\App\Models\UserGridConfig whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUserGridConfig {}
}

