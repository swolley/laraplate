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


namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string $username The username of the user
 * @property string|null $lang The language of the user
 * @property string|null $last_login_at The last login date of the user
 * @property string|null $license_id The license id of the user
 * @property string|null $two_factor_secret The two factor secret of the user
 * @property string|null $two_factor_recovery_codes The two factor recovery codes of the user
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property string|null $locked_at The date and time when the entity was locked
 * @property string|null $locked_user_id The user who locked the entity
 * @property bool $is_locked Whether the entity is locked
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Approval> $approvals
 * @property-read int|null $approvals_count
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
 * @property-read \Modules\Core\Models\Pivot\ModelHasRole|null $pivot
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Modules\Core\Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User lockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User unlockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereLang($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereLicenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereLockedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperUser {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $user_id The user that the author belongs to
 * @property int $entity_id
 * @property int $preset_id The preset that the author belongs to
 * @property string $name The name of the author
 * @property array $components The author contents
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property-read \Modules\Cms\Models\Pivot\Authorable|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Content> $contents
 * @property-read int|null $contents_count
 * @property-read \Modules\Cms\Models\Entity $entity
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Modules\Cms\Models\Preset $preset
 * @property \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @property-read mixed $type
 * @property-read \Modules\Core\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Modules\Cms\Database\Factories\AuthorFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author whereComponents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author wherePresetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author withAllTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author withAnyTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author withoutTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Author withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperAuthor {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $entity_id
 * @property int $preset_id The preset that the category belongs to
 * @property int|null $parent_id The parent category
 * @property int|null $parent_entity_id The entity that the parent category belongs to
 * @property string $name The name of the category
 * @property string $slug The slug of the category
 * @property array $components The category contents
 * @property int|null $persistence The persistence in days of the content in the category
 * @property string|null $logo The logo of the category
 * @property string|null $logo_full The full logo of the category
 * @property bool $is_active Whether the category is active
 * @property int $order_column The order of the category
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property string|null $locked_at The date and time when the entity was locked
 * @property string|null $locked_user_id The user who locked the entity
 * @property bool $is_locked Whether the entity is locked
 * @property string $valid_from
 * @property string|null $valid_to
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $children
 * @property-read int|null $children_count
 * @property-read \Modules\Cms\Models\Pivot\Categorizable|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Content> $contents
 * @property-read int|null $contents_count
 * @property-read \Modules\Cms\Models\Entity|null $entity
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read array|null $preview
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Modification> $modifications
 * @property-read int|null $modifications_count
 * @property-read \Modules\Cms\Models\Category|null $parent
 * @property-read mixed $path
 * @property-read \Modules\Cms\Models\Preset|null $preset
 * @property \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @property-read mixed $type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $ancestors The model's recursive parents.
 * @property-read int|null $ancestors_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $ancestorsAndSelf The model's recursive parents and itself.
 * @property-read int|null $ancestors_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $bloodline The model's ancestors, descendants and itself.
 * @property-read int|null $bloodline_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $childrenAndSelf The model's direct children and itself.
 * @property-read int|null $children_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $descendants The model's recursive children.
 * @property-read int|null $descendants_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $descendantsAndSelf The model's recursive children and itself.
 * @property-read int|null $descendants_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $parentAndSelf The model's direct parent and itself.
 * @property-read int|null $parent_and_self_count
 * @property-read \Modules\Cms\Models\Category|null $rootAncestor The model's topmost parent.
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $siblings The parent's other children.
 * @property-read int|null $siblings_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $siblingsAndSelf All the parent's children.
 * @property-read int|null $siblings_and_self_count
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, static> all($columns = ['*'])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category breadthFirst()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category depthFirst()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category doesntHaveChildren()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category draft()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category expired()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category expiredAt(\Illuminate\Support\Carbon $date)
 * @method static \Modules\Cms\Database\Factories\CategoryFactory factory($count = null, $state = [])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, static> get($columns = ['*'])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category getExpressionGrammar()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category hasChildren()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category hasParent()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category isLeaf()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category isRoot()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category locked()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category lockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category newModelQuery()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Category onlyTrashed()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category ordered(string $direction = 'asc')
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category priorityOrdered(string $direction = 'asc')
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category published()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category query()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category scheduled()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category tree($maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category treeOf(\Illuminate\Database\Eloquent\Model|callable $constraint, $maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category unlocked()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category unlockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category valid()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category validAt(\Illuminate\Support\Carbon $date)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category validityOrdered()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereComponents($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereCreatedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereDeletedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereDepth($operator, $value = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereEntityId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereIsActive($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereIsDeleted($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereIsLocked($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereLockedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereLockedUserId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereLogo($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereLogoFull($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereName($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereOrderColumn($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereParentEntityId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereParentId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category wherePersistence($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category wherePresetId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereSlug($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereUpdatedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereValidFrom($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category whereValidTo($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category withAllTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category withAllTagsOfAnyType($tags)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category withAnyTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category withAnyTagsOfAnyType($tags)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category withGlobalScopes(array $scopes)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category withRelationshipExpression($direction, callable $constraint, $initialDepth, $from = null, $maxDepth = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Category withTrashed()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Cms\Models\Category withoutTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Category withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperCategory {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @property int $id
 * @property int $entity_id
 * @property int $preset_id The preset that the content belongs to
 * @property string $title The title of the content
 * @property array $components The content contents
 * @property string $slug The slug of the content
 * @property int $order_column The order of the content
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property string|null $locked_at The date and time when the entity was locked
 * @property string|null $locked_user_id The user who locked the entity
 * @property bool $is_locked Whether the entity is locked
 * @property string|null $valid_from
 * @property string|null $valid_to
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $allMedia
 * @property-read int|null $all_media_count
 * @property-read \Modules\Cms\Models\Pivot\Categorizable|\Modules\Cms\Models\Pivot\Authorable|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Author> $authors
 * @property-read int|null $authors_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property \Spatie\MediaLibrary\MediaCollections\Models\Media|null $cover
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\ModelEmbedding> $embeddings
 * @property-read int|null $embeddings_count
 * @property-read \Modules\Cms\Models\Entity $entity
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read array|null $preview
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Location> $locations
 * @property-read int|null $locations_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Modification> $modifications
 * @property-read int|null $modifications_count
 * @property-read mixed $path
 * @property-read \Modules\Cms\Models\Preset $preset
 * @property \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $trashedMedia
 * @property-read int|null $trashed_media_count
 * @property-read mixed $type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content draft()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content expiredAt(\Illuminate\Support\Carbon $date)
 * @method static \Modules\Cms\Database\Factories\ContentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content lockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content priorityOrdered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content unlockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content valid()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content validAt(\Illuminate\Support\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content validityOrdered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereComponents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereLockedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content wherePresetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereValidTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withAllTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withAnyTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withoutTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperContent {}
}

namespace Modules\Cms\Models\Contents{
/**
 * 
 *
 * @property int $id
 * @property int $entity_id
 * @property int $preset_id The preset that the content belongs to
 * @property string $title The title of the content
 * @property array $components The content contents
 * @property string $slug The slug of the content
 * @property int $order_column The order of the content
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property string|null $locked_at The date and time when the entity was locked
 * @property string|null $locked_user_id The user who locked the entity
 * @property bool $is_locked Whether the entity is locked
 * @property string|null $valid_from
 * @property string|null $valid_to
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $allMedia
 * @property-read int|null $all_media_count
 * @property-read \Modules\Cms\Models\Pivot\Categorizable|\Modules\Cms\Models\Pivot\Authorable|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Author> $authors
 * @property-read int|null $authors_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property \Spatie\MediaLibrary\MediaCollections\Models\Media|null $cover
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\ModelEmbedding> $embeddings
 * @property-read int|null $embeddings_count
 * @property-read \Modules\Cms\Models\Entity $entity
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read array|null $preview
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Location> $locations
 * @property-read int|null $locations_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Modification> $modifications
 * @property-read int|null $modifications_count
 * @property-read mixed $path
 * @property-read \Modules\Cms\Models\Preset $preset
 * @property \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $trashedMedia
 * @property-read int|null $trashed_media_count
 * @property-read mixed $type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article draft()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article expiredAt(\Illuminate\Support\Carbon $date)
 * @method static \Modules\Cms\Database\Factories\ContentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article lockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article priorityOrdered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article unlockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article valid()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article validAt(\Illuminate\Support\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article validityOrdered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereComponents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereLockedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article wherePresetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article whereValidTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article withAllTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article withAnyTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article withoutTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Article withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperArticle {}
}

namespace Modules\Cms\Models\Contents{
/**
 * 
 *
 * @property int $id
 * @property int $entity_id
 * @property int $preset_id The preset that the content belongs to
 * @property string $title The title of the content
 * @property array $components The content contents
 * @property string $slug The slug of the content
 * @property int $order_column The order of the content
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property string|null $locked_at The date and time when the entity was locked
 * @property string|null $locked_user_id The user who locked the entity
 * @property bool $is_locked Whether the entity is locked
 * @property string|null $valid_from
 * @property string|null $valid_to
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $allMedia
 * @property-read int|null $all_media_count
 * @property-read \Modules\Cms\Models\Pivot\Categorizable|\Modules\Cms\Models\Pivot\Authorable|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Author> $authors
 * @property-read int|null $authors_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property \Spatie\MediaLibrary\MediaCollections\Models\Media|null $cover
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\ModelEmbedding> $embeddings
 * @property-read int|null $embeddings_count
 * @property-read \Modules\Cms\Models\Entity $entity
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read array|null $preview
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Location> $locations
 * @property-read int|null $locations_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Modification> $modifications
 * @property-read int|null $modifications_count
 * @property-read mixed $path
 * @property-read \Modules\Cms\Models\Preset $preset
 * @property \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $trashedMedia
 * @property-read int|null $trashed_media_count
 * @property-read mixed $type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event draft()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event expiredAt(\Illuminate\Support\Carbon $date)
 * @method static \Modules\Cms\Database\Factories\ContentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event lockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event priorityOrdered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event unlockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event valid()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event validAt(\Illuminate\Support\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event validityOrdered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereComponents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereLockedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event wherePresetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event whereValidTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event withAllTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event withAnyTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event withoutTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Event withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperEvent {}
}

namespace Modules\Cms\Models\Contents{
/**
 * 
 *
 * @property int $id
 * @property int $entity_id
 * @property int $preset_id The preset that the content belongs to
 * @property string $title The title of the content
 * @property array $components The content contents
 * @property string $slug The slug of the content
 * @property int $order_column The order of the content
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property string|null $locked_at The date and time when the entity was locked
 * @property string|null $locked_user_id The user who locked the entity
 * @property bool $is_locked Whether the entity is locked
 * @property string|null $valid_from
 * @property string|null $valid_to
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $allMedia
 * @property-read int|null $all_media_count
 * @property-read \Modules\Cms\Models\Pivot\Categorizable|\Modules\Cms\Models\Pivot\Authorable|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Author> $authors
 * @property-read int|null $authors_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property \Spatie\MediaLibrary\MediaCollections\Models\Media|null $cover
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\ModelEmbedding> $embeddings
 * @property-read int|null $embeddings_count
 * @property-read \Modules\Cms\Models\Entity $entity
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read array|null $preview
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Location> $locations
 * @property-read int|null $locations_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Modification> $modifications
 * @property-read int|null $modifications_count
 * @property-read mixed $path
 * @property-read \Modules\Cms\Models\Preset $preset
 * @property \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $trashedMedia
 * @property-read int|null $trashed_media_count
 * @property-read mixed $type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia draft()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia expiredAt(\Illuminate\Support\Carbon $date)
 * @method static \Modules\Cms\Database\Factories\ContentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia lockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia priorityOrdered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia unlockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia valid()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia validAt(\Illuminate\Support\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia validityOrdered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereComponents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereLockedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia wherePresetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia whereValidTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia withAllTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia withAnyTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia withoutTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Multimedia withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperMultimedia {}
}

namespace Modules\Cms\Models\Contents{
/**
 * 
 *
 * @property int $id
 * @property int $entity_id
 * @property int $preset_id The preset that the content belongs to
 * @property string $title The title of the content
 * @property array $components The content contents
 * @property string $slug The slug of the content
 * @property int $order_column The order of the content
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property string|null $locked_at The date and time when the entity was locked
 * @property string|null $locked_user_id The user who locked the entity
 * @property bool $is_locked Whether the entity is locked
 * @property string|null $valid_from
 * @property string|null $valid_to
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $allMedia
 * @property-read int|null $all_media_count
 * @property-read \Modules\Cms\Models\Pivot\Categorizable|\Modules\Cms\Models\Pivot\Authorable|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Author> $authors
 * @property-read int|null $authors_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property \Spatie\MediaLibrary\MediaCollections\Models\Media|null $cover
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\ModelEmbedding> $embeddings
 * @property-read int|null $embeddings_count
 * @property-read \Modules\Cms\Models\Entity $entity
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read array|null $preview
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Location> $locations
 * @property-read int|null $locations_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Modification> $modifications
 * @property-read int|null $modifications_count
 * @property-read mixed $path
 * @property-read \Modules\Cms\Models\Preset $preset
 * @property \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Modules\Cms\Models\Media> $trashedMedia
 * @property-read int|null $trashed_media_count
 * @property-read mixed $type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey draft()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey expiredAt(\Illuminate\Support\Carbon $date)
 * @method static \Modules\Cms\Database\Factories\ContentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey lockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey priorityOrdered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey unlockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey valid()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey validAt(\Illuminate\Support\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey validityOrdered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereComponents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereLockedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey wherePresetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey whereValidTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey withAllTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey withAnyTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey withoutTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Contents\Survey withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperSurvey {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name The name of the entity
 * @property string $slug The slug of the entity
 * @property \Modules\Cms\Casts\EntityType $type The type of the entity
 * @property bool $is_active Whether the entity is active
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property string|null $locked_at The date and time when the entity was locked
 * @property string|null $locked_user_id The user who locked the entity
 * @property bool $is_locked Whether the entity is locked
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Content> $contents
 * @property-read int|null $contents_count
 * @property-read mixed $path
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Preset> $presets
 * @property-read int|null $presets_count
 * @method static \Modules\Cms\Database\Factories\EntityFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity lockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity unlockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereLockedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperEntity {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @property-read object $options
 * @property int $id
 * @property string $name The name of the field
 * @property \Modules\Cms\Casts\FieldType $type The type of the field
 * @property bool $is_slug Whether the field takes part in the slug
 * @property bool $is_active Whether the field is active
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Modules\Cms\Models\Pivot\Fieldable|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Preset> $presets
 * @property-read int|null $presets_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereIsSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperField {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @method static whereDistance(Point $point, float $distance)
 * @method static orderByDistance(Point $point, string $direction = 'asc')
 * @method static whereDistanceSphere(Point $point, float $distance)
 * @method static orderByDistanceSphere(Point $point, string $direction = 'asc')
 * @method static whereWithin(Polygon $polygon)
 * @method static whereNotWithin(Polygon $polygon)
 * @method static whereContains(Polygon $polygon)
 * @method static whereNotContains(Polygon $polygon)
 * @method static whereEquals(Point $point)
 * @property int $id
 * @property string $name The friendly name of the location
 * @property string $slug The slug of the location
 * @property string|null $address The address of the location
 * @property string|null $city The city of the location
 * @property string|null $province The province of the location
 * @property string $country The country of the location
 * @property string|null $postcode The postcode of the location
 * @property string|null $zone The zone of the location
 * @property \MatanYadaev\EloquentSpatial\Objects\Geometry|null $geolocation The geolocation of the location
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property string|null $locked_at The date and time when the entity was locked
 * @property string|null $locked_user_id The user who locked the entity
 * @property bool $is_locked Whether the entity is locked
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Content> $contents
 * @property-read int|null $contents_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\ModelEmbedding> $embeddings
 * @property-read int|null $embeddings_count
 * @property-read mixed $path
 * @property \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @method static \Modules\Cms\Database\Factories\LocationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location orderByDistanceSphere(\Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $column, \Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $geometryOrColumn, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereCrosses(\Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $column, \Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $geometryOrColumn)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereDisjoint(\Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $column, \Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $geometryOrColumn)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereDistanceSphere(\Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $column, \Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $geometryOrColumn, string $operator, int|float $value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereGeolocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereIntersects(\Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $column, \Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $geometryOrColumn)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereLockedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereOverlaps(\Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $column, \Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $geometryOrColumn)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location wherePostcode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereProvince($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereSrid(\Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $column, string $operator, int|float $value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereTouches(\Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $column, \Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $geometryOrColumn)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location whereZone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location withAllTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location withAnyTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location withCentroid(\Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $column, string $alias = 'centroid')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location withDistance(\Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $column, \Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $geometryOrColumn, string $alias = 'distance')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location withDistanceSphere(\Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $column, \Illuminate\Contracts\Database\Query\Expression|\MatanYadaev\EloquentSpatial\Objects\Geometry|string $geometryOrColumn, string $alias = 'distance')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location withoutTags(\ArrayAccess|\Modules\Cms\Models\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Location withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperLocation {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string|null $uuid The UUID of the media
 * @property string $collection_name The collection name of the media
 * @property string $name The name of the media
 * @property string $file_name The file name of the media
 * @property string|null $mime_type The mime type of the media
 * @property string $disk The disk of the media
 * @property string|null $conversions_disk The conversions disk of the media
 * @property int $size The size of the media
 * @property array<array-key, mixed> $manipulations The manipulations of the media
 * @property array<array-key, mixed> $custom_properties The custom properties of the media
 * @property array<array-key, mixed> $generated_conversions The generated conversions of the media
 * @property array<array-key, mixed> $responsive_images The responsive images of the media
 * @property int $order_column The order column of the media
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property-read mixed $extension
 * @property-read \Illuminate\Support\Carbon|null $expires_at
 * @property-read mixed $human_readable_size
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $model
 * @property-read mixed $original_url
 * @property-read mixed $preview_url
 * @property-read mixed $type
 * @method static \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, static> all($columns = ['*'])
 * @method static \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, static> get($columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereCollectionName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereConversionsDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereCustomProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereGeneratedConversions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereManipulations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereResponsiveImages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Media withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperMedia {}
}

namespace Modules\Cms\Models\Pivot{
/**
 * 
 *
 * @property int $content_id
 * @property int $author_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable whereContentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperAuthorable {}
}

namespace Modules\Cms\Models\Pivot{
/**
 * 
 *
 * @property int $content_id The content that the categorizable belongs to
 * @property int $category_id The category that the categorizable belongs to
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable whereContentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperCategorizable {}
}

namespace Modules\Cms\Models\Pivot{
/**
 * 
 *
 * @property int $id
 * @property int $preset_id
 * @property int $field_id
 * @property bool $is_required Whether the field is required
 * @property int $order_column The order of the field
 * @property array<array-key, mixed>|null $default The default value of the field
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property string|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereFieldId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereIsRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable wherePresetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperFieldable {}
}

namespace Modules\Cms\Models\Pivot{
/**
 * 
 *
 * @property int $content_id
 * @property int $related_content_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable whereContentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable whereRelatedContentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperRelatable {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @property int $id
 * @property int $entity_id
 * @property string $name The name of the preset
 * @property bool $is_active Whether the preset is active
 * @property int|null $template_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Content> $contents
 * @property-read int|null $contents_count
 * @property-read \Modules\Cms\Models\Entity $entity
 * @property-read \Modules\Cms\Models\Pivot\Fieldable|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Field> $fields
 * @property-read int|null $fields_count
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read array|null $preview
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Modification> $modifications
 * @property-read int|null $modifications_count
 * @property-read \Modules\Cms\Models\Template|null $template
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset whereTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperPreset {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name The name of the tag
 * @property string $slug The slug of the tag
 * @property string|null $type The type of the tag
 * @property int $order_column The order of the tag
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property-read mixed $path
 * @method static \Modules\Cms\Database\Factories\TagFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Tag withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperTag {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name The name of the template
 * @property string $content The blade template content
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property string|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperTemplate {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @property \Modules\Core\Casts\FiltersGroup $filters
 * @property \Modules\Core\Casts\Sort $sort
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Modules\Core\Models\Permission|null $permission
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ACL forPermission($permission_id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ACL newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ACL newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ACL onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ACL query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ACL withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ACL withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperACL {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name The name of the cron job
 * @property string $command The command of the cron job
 * @property array<array-key, mixed> $parameters The parameters of the cron job
 * @property \Cron\CronExpression $schedule The schedule of the cron job
 * @property bool $is_active Is the cron job active
 * @property string|null $description The description of the cron job
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Modules\Core\Database\Factories\CronJobFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob lockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob unlockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereCommand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereParameters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereSchedule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\CronJob withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperCronJob {}
}

namespace Modules\Core\Models{
/**
 * 
 *
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
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperDynamicEntity {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @property string $id The unique identifier for the license
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property string $valid_from
 * @property string|null $valid_to
 * @property-read \Modules\Core\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License draft()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License expiredAt(\Illuminate\Support\Carbon $date)
 * @method static \Modules\Core\Database\Factories\LicenseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License valid()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License validAt(\Illuminate\Support\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License validityOrdered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\License whereValidTo($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperLicense {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property array<array-key, mixed> $embedding The generated embedding of the model
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model $model
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding whereEmbedding($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\ModelEmbedding whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperModelEmbedding {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $modifiable_id The id of the modifiable model
 * @property string|null $modifiable_type The type of the modifiable model
 * @property int|null $modifier_id The id of the modifier model
 * @property string|null $modifier_type The type of the modifier model
 * @property bool $active Whether the modification is active
 * @property bool $is_update Whether the modification is an update
 * @property int $approvers_required The number of approvers required
 * @property int $disapprovers_required The number of disapprovers required
 * @property string $md5 The md5 hash of the modifications
 * @property array<array-key, mixed> $modifications The modifications
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Approval> $approvals
 * @property-read int|null $approvals_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Disapproval> $disapprovals
 * @property-read int|null $disapprovals_count
 * @property-read int $approvals_remaining
 * @property-read int $approvers_remaining
 * @property-read int $disapprovals_remaining
 * @property-read int $disapprovers_remaining
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $modifiable
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $modifier
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
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperModification {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name The name of the permission
 * @property string $guard_name The guard name of the permission
 * @property string|null $description The description of the permission
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property string|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property string|null $connection_name
 * @property string|null $table_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereConnectionName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereGuardName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereTableName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Permission withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperPermission {}
}

namespace Modules\Core\Models\Pivot{
/**
 * 
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Pivot\ModelHasRole newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Pivot\ModelHasRole newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Pivot\ModelHasRole query()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperModelHasRole {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name The name of the role
 * @property string $guard_name The guard name of the role
 * @property string|null $description The description of the role
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property string|null $locked_at The date and time when the entity was locked
 * @property string|null $locked_user_id The user who locked the entity
 * @property bool $is_locked Whether the entity is locked
 * @property int|null $parent_id The parent id of the role
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $children
 * @property-read int|null $children_count
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Modules\Core\Models\Role|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Modules\Core\Models\Pivot\ModelHasRole|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $ancestors The model's recursive parents.
 * @property-read int|null $ancestors_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $ancestorsAndSelf The model's recursive parents and itself.
 * @property-read int|null $ancestors_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $bloodline The model's ancestors, descendants and itself.
 * @property-read int|null $bloodline_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $childrenAndSelf The model's direct children and itself.
 * @property-read int|null $children_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $descendants The model's recursive children.
 * @property-read int|null $descendants_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $descendantsAndSelf The model's recursive children and itself.
 * @property-read int|null $descendants_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $parentAndSelf The model's direct parent and itself.
 * @property-read int|null $parent_and_self_count
 * @property-read \Modules\Core\Models\Role|null $rootAncestor The model's topmost parent.
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $siblings The parent's other children.
 * @property-read int|null $siblings_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $siblingsAndSelf All the parent's children.
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
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role lockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role newModelQuery()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Role onlyTrashed()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role permission($permissions, $without = false)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role query()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role tree($maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role treeOf(\Illuminate\Database\Eloquent\Model|callable $constraint, $maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role unlocked()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role unlockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereCreatedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereDeletedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereDepth($operator, $value = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereDescription($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereGuardName($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereIsDeleted($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|\Modules\Core\Models\Role whereIsLocked($value)
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
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperRole {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name The name of the setting
 * @property array<array-key, mixed> $value The value of the setting
 * @property bool $encrypted Is the value encrypted
 * @property array<array-key, mixed>|null $choices Constrained available values
 * @property \Modules\Core\Casts\SettingTypeEnum $type The type of the setting
 * @property string $group_name The group name of the setting
 * @property string $description The description of the setting
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting whereValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Setting withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperSetting {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @property BelongsToMany $roles
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string $username The username of the user
 * @property string|null $lang The language of the user
 * @property string|null $last_login_at The last login date of the user
 * @property string|null $license_id The license id of the user
 * @property string|null $two_factor_secret The two factor secret of the user
 * @property string|null $two_factor_recovery_codes The two factor recovery codes of the user
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property bool $is_deleted Whether the entity is deleted
 * @property string|null $locked_at The date and time when the entity was locked
 * @property string|null $locked_user_id The user who locked the entity
 * @property bool $is_locked Whether the entity is locked
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Approval\Models\Approval> $approvals
 * @property-read int|null $approvals_count
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
 * @property-read \Modules\Core\Models\Pivot\ModelHasRole|null $pivot
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Modules\Core\Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User lockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User unlockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereLang($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereLicenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereLockedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\User withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @property int $id
 * @property int|null $user_id The user id of the user grid config
 * @property string $grid_name The grid name of the user grid config
 * @property string $layout_name The layout name of the user grid config
 * @property bool $is_public The is public of the user grid config
 * @property array<array-key, mixed> $config The config of the user grid config
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property-read \Modules\Core\Models\User|null $user
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
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	final class IdeHelperUserGridConfig {}
}

