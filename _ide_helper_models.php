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


namespace Modules\Cms\Models{
/**
 * 
 *
 * @mixin IdeHelperContent
 * @property int $id
 * @property int $entity_id
 * @property int $preset_id
 * @property int|null $order_column
 * @property array<array-key, mixed> $components
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $locked_at
 * @property string|null $locked_user_id
 * @property string $valid_from
 * @property string|null $valid_to
 * @property-read \Modules\Cms\Models\Pivot\Categorizable|\Modules\Cms\Models\Pivot\Authorable|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Author> $authors
 * @property-read int|null $authors_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Cms\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property mixed $cover
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\ModelEmbedding> $embeddings
 * @property-read int|null $embeddings_count
 * @property-read \Modules\Cms\Models\Entity $entity
 * @property-read \Modules\Core\Models\Version|null $firstVersion
 * @property-read \Modules\Core\Models\Version|null $lastVersion
 * @property-read \Modules\Core\Models\Version|null $latestVersion
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read mixed $name
 * @property-read \Modules\Cms\Models\Preset $preset
 * @property \Illuminate\Database\Eloquent\Collection<int, \Spatie\Tags\Tag> $tags
 * @property-read int|null $tags_count
 * @property object|array $values
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content draft()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content expiredAt(\Illuminate\Support\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content locked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content lockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content unlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content unlockedBy(\Illuminate\Foundation\Auth\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content validAt(\Illuminate\Support\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereComponents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereLockedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content wherePresetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content whereValidTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withAllTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withAnyTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withoutTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Content withoutTrashed()
 */
	class Content extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @mixin IdeHelperEntity
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Cms\Models\Preset> $presets
 * @property-read int|null $presets_count
 * @method static \Modules\CMS\Database\Factories\EntityFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Entity withoutTrashed()
 */
	class Entity extends \Eloquent {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @mixin IdeHelperField
 * @property int $id
 * @property string $name
 * @property \Modules\Cms\Casts\FieldType $type
 * @property string $options
 * @property bool $is_active
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Field withoutTrashed()
 */
	class Field extends \Eloquent {}
}

namespace Modules\Cms\Models\Pivot{
/**
 * 
 *
 * @mixin IdeHelperAuthorable
 * @property int $content_id
 * @property int $author_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable whereContentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Authorable whereUpdatedAt($value)
 */
	class Authorable extends \Eloquent {}
}

namespace Modules\Cms\Models\Pivot{
/**
 * 
 *
 * @mixin IdeHelperCategorizable
 * @property int $content_id
 * @property int $entity_id
 * @property int $category_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable whereContentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Categorizable whereUpdatedAt($value)
 */
	class Categorizable extends \Eloquent {}
}

namespace Modules\Cms\Models\Pivot{
/**
 * 
 *
 * @mixin IdeHelperFieldable
 * @property int $id
 * @property int $preset_id
 * @property int $field_id
 * @property bool $is_required
 * @property int $order_column
 * @property array<array-key, mixed>|null $default
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Illuminate\Support\Carbon $updated_at
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereFieldId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereIsRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable wherePresetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Fieldable whereUpdatedAt($value)
 */
	class Fieldable extends \Eloquent {}
}

namespace Modules\Cms\Models\Pivot{
/**
 * 
 *
 * @mixin IdeHelperRelatable
 * @property int $content_id
 * @property int $related_content_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable whereContentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable whereRelatedContentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Pivot\Relatable whereUpdatedAt($value)
 */
	class Relatable extends \Eloquent {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @mixin IdeHelperPreset
 * @property int $id
 * @property int $entity_id
 * @property string $name
 * @property bool $is_active
 * @property int|null $template_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset whereTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Preset withoutTrashed()
 */
	class Preset extends \Eloquent {}
}

namespace Modules\Cms\Models{
/**
 * 
 *
 * @mixin IdeHelperTemplate
 * @property int $id
 * @property string $name
 * @property string $content
 * @property \Carbon\CarbonImmutable $created_at
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Cms\Models\Template whereUpdatedAt($value)
 */
	class Template extends \Eloquent {}
}

namespace Modules\Core\Models{
/**
 * 
 *
 * @mixin IdeHelperCronJob
 * @property int $id
 * @property string $name
 * @property string $command
 * @property array<array-key, mixed> $parameters
 * @property \Cron\CronExpression $schedule
 * @property bool $is_active
 * @property string|null $description
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
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
 * @property-read \Modules\Core\Models\User|null $user
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
 * @property array<array-key, mixed> $embedding
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
 * @property array<array-key, mixed> $modifications
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
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
 * @property-read \Modules\Core\Casts\ActionEnum|null $action
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, \Modules\Core\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Core\Models\User> $users
 * @property-read int|null $users_count
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

namespace Modules\Core\Models\Pivot{
/**
 * 
 *
 * @mixin IdeHelperModelHasRole
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Pivot\ModelHasRole newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Pivot\ModelHasRole newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\Modules\Core\Models\Pivot\ModelHasRole query()
 */
	class ModelHasRole extends \Eloquent {}
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
 * @property-read \Modules\Core\Models\Pivot\ModelHasRole|null $pivot
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
 * @property array<array-key, mixed> $value
 * @property bool $encrypted
 * @property array<array-key, mixed>|null $choices
 * @property \Modules\Core\Casts\SettingTypeEnum $type
 * @property string $group_name
 * @property string $description
 * @property \Carbon\CarbonImmutable $created_at
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
 * @property \Carbon\CarbonImmutable|null $created_at
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
 * @property array<array-key, mixed> $config
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Illuminate\Support\Carbon $updated_at
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
 */
	class UserGridConfig extends \Eloquent {}
}

