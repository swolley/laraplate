<?php

namespace Modules\Cms\Models;

use Spatie\Tags\HasTags;
use Parental\HasChildren;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Modules\Cms\Helpers\HasPath;
use Modules\Cms\Helpers\HasSlug;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Support\Collection;
use Modules\Core\Cache\Searchable;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Helpers\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Modules\Cms\Models\Pivot\Relatable;
use Modules\Cms\Models\Pivot\Authorable;
use Modules\Core\Helpers\HasValidations;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Locking\Traits\HasLocks;
use Spatie\EloquentSortable\SortableTrait;
use Modules\Cms\Models\Pivot\Categorizable;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Locking\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperContent
 */
class Content extends Model implements HasMedia
{
	use SoftDeletes, HasTags, HasValidity, HasLocks, HasOptimisticLocking, HasVersions, HasChildren, SortableTrait, InteractsWithMedia, HasSlug, HasPath, HasValidations, Searchable {
		prepareElasticDocument as protected prepareElasticDocumentTrait;
		getRules as protected getRulesTrait;
	}

	protected $fillable = ['valid_from', 'valid_to', 'preset_id', 'entity_id', 'components'];

	protected $with = ['entity', 'authors'];

	protected $hidden = ['preset_id', 'entity_id', 'created_at', 'updated_at', 'deleted_at', 'entity', 'components'];

	protected $childColumn = 'entity_id';

	protected $sortable = [
		'order_column_name' => 'order_column',
		'sort_when_creating' => true,
	];

	protected $appends = ['values'];

	protected function casts(): array
	{
		return [
			'components' => 'json',
			'preset_id' => 'integer',
			'created_at' => 'immutable_datetime',
			'updated_at' => 'datetime',
			'deleted_at' => 'datetime',
		];
	}

	protected function getChildTypes(): array
	{
		$child_types = [];
		foreach (static::getAvailableEntities() as $entity) {
			$class_name = Str::studly($entity->name);
			$full_class_name = 'CMS\\Models\\Contents\\' . $class_name;
			if (!class_exists($full_class_name)) {
				$full_class_name = 'CMS_Content' . $class_name;
				if (!class_exists($full_class_name)) {
					eval("class {$full_class_name} extends \App\Models\Content {
							use Parental\HasParent;
						}");
				}
			}
			$child_types[$entity->id] = $full_class_name;
		}
		return $child_types;
	}

	public function __construct(array $attributes = [])
	{
		parent::__construct($attributes);

		if (static::class !== Content::class && !$this->entity_id) {
			$this->entity()->associate(static::getAvailableEntities()->firstWhere('name', Str::snake(class_basename($this::class))));
		}
	}

	#[\Override]
	protected static function boot()
	{
		parent::boot();

		// static::addGlobalScope('always_with_entity', function (Builder $builder) {
		// 	$builder->with('entity');
		// });

		static::addGlobalScope('ordered', function (Builder $builder) {
			$builder->ordered('asc')->orderBy('valid_from', 'desc')->orderBy('created_at', 'desc');
			if (request()?->is('api/*')) {
				$builder->with('all_related');
			}
		});

		static::saving(function ($content) {
			if ($content->preset_id) {
				$preset = Preset::find($content->preset_id, ['entity_id']);
				if ($content->entity_id && $content->entity_id !== $preset->entity_id) {
					throw new \UnexpectedValueException("Entity mismatch: {$content->entity->name} is not compatible with {$preset->name}");
				}
				$content->entity_id = $preset->entity_id;
			}
		});
	}

	protected function name(): Attribute
	{
		return Attribute::make(
			get: function () {
				$field = $this->preset?->fields()->firstWhere('is_slug', true);
				return $field ? $this->values->{$field->name} : '';
			},
		);
	}

	// /**
	//  * 
	//  * @return string
	//  */
	// public function getNameAttribute(): string
	// {
	// 	$field = $this->preset?->fields()->firstWhere('is_slug', true);
	// 	return $field ? $this->values->{$field->name} : '';
	// }

	protected function values(): Attribute
	{
		return Attribute::make(
			get: fn() => (object) $this->fields()->mapWithKeys(function (Field $field) {
				$components = (object) $this->components;
				return [$field->name => property_exists($components, $field->name) ? $components->{$field->name} : $field->default ?? null];
			}),
			set: fn(array|object $values) => (object) $this->fields()->mapWithKeys(function (Field $field) use ($values) {
				return [$field->name => data_get($values, $field->name) ?? $field->default ?? null];
			}),
		);
	}

	private function fields(): Collection
	{
		return $this->preset?->fields ?? collect();
	}

	public function entity(): BelongsTo
	{
		return $this->belongsTo(Entity::class)->select(['id', 'name', 'slug'])->withTrashed();
	}

	/**
	 * The folders that belong to the content.
	 */
	public function categories(): BelongsToMany
	{
		return $this->belongsToMany(Category::class, 'categorizables')->using(Categorizable::class)->withTimestamps()->where('entity_id', $this->entity_id);
	}

	/**
	 * The author that belongs to the content.
	 */
	public function authors(): BelongsToMany
	{
		return $this->belongsToMany(Author::class, 'authorables')->using(Authorable::class)->withTimestamps()->select(['id', 'name'])->withTrashed();
	}

	/**
	 * @return BelongsTo<Preset>
	 */
	public function preset(): BelongsTo
	{
		return $this->belongsTo(Preset::class)->withTrashed()->where('entity_id', $this->entity_id);
	}

	public function related(?Entity $entity = null): BelongsToMany
	{
		$relation = $this->belongsToMany(Content::class, 'relatables')->using(Relatable::class)->withTimestamps();
		if ($entity) {
			$relation->where('entity_id', $entity->id);
		}
		return $relation;
	}

	protected function all_related(?Entity $entity = null): BelongsToMany
	{
		return $this->related($entity)->orWhere(function ($query) use ($entity) {
			$query->where('related_content_id', $this->id);
			if ($entity) {
				$query->where('entity_id', $entity->id);
			}
		});
	}

	protected static function getAvailableEntities(): Collection
	{
		$cachedEntities = Cache::forever((new Entity())->getCacheKey(), Entity::all());
		if (is_iterable($cachedEntities)) {
			return collect($cachedEntities);
		}
		return collect();
	}

	protected function prepareElasticDocument(): array
	{
		$document = $this->prepareElasticDocumentTrait();
		$document['author_id'] = $this->author_id;
		$document['author_name'] = $this->author->name;
		$document['model_type_id'] = $this->model_type->id;

		return $document;
	}

	#[\Override]
	public function __get($key)
	{
		$value = parent::__get($key);
		if ($value == null) {
			return $value;
		}

		return data_get($this->values, $key);
	}

	#[\Override]
	public function __set($key, $value)
	{
		if (array_key_exists($key, $this->attributes)) {
			parent::__set($key, $value);
			return;
		}
		if (array_key_exists($key, $this->values)) {
			data_set($this->values, $key, $value);
		}
	}

	#[\Override]
	public function toArray(): array
	{
		$content = parent::toArray();
		if (isset($content['values'])) {
			$values = $content['values'];
			unset($content['values']);
			$content = array_merge($content, $values);
		} else if (isset($this->values)) {
			$content = array_merge($content, $this->values->toArray());
		}

		return $content;
	}

	public function registerMediaCollections(): void
	{
		$this->addMediaCollection('cover')->singleFile();
		$this->addMediaCollection('images');
		$this->addMediaCollection('videos')
			->extractVideoFrameAtSecond(2);
		$this->addMediaCollection('audios');
		$this->addMediaCollection('files');
	}


	public function registerMediaConversions(?Media $media = null): void
	{
		$this->addMediaConversion('thumb')
			->performOnCollections('images', 'videos', 'cover')
			->width(300)
			->height(300)
			->sharpen(10)
			->fit(Fit::Fill, 300, 300);
	}

	protected function cover(): Attribute
	{
		return Attribute::make(
			get: fn() => $this->getFirstMediaUrl('cover'),
			set: fn($value) => $this->addMedia($value)->toMediaCollection('cover'),
		);
	}

	public function getRules()
	{
		$fields = [];
		foreach ($this->fields() as $field) {
			$rule = $field->type->getRule();
			if ($field->required) {
				$rule .= '|required';
			}
			if (isset($field->options->min)) {
				$rule .= '|min:' . $field->options->min;
			}
			if (isset($field->options->max)) {
				$rule .= '|max:' . $field->options->max;
			}
			$fields['values.' . $field->name] = trim($rule, '|');
		}

		$rules = $this->getRulesTrait();
		$rules[static::DEFAULT_RULE] = array_merge($rules[static::DEFAULT_RULE], [
			'values' => 'required',
			'entity_id' => 'required|exists:entities,id',
			'preset_id' => 'required|exists:presets,id',
		]);
		return $rules;
	}

	public function isPublished(): bool
	{
		return $this->valid_from <= now() && ($this->valid_to === null || $this->valid_to >= now());
	}

	public function isExpired(): bool
	{
		return $this->valid_to !== null && $this->valid_to < now();
	}

	public function isDraft(): bool
	{
		return $this->valid_from === null;
	}

	public function isScheduled(): bool
	{
		return $this->valid_from !== null && $this->valid_from > now();
	}

	public function scopePublished(Builder $query): Builder
	{
		return $query->where('valid_from', '<=', now())->where(function ($query) {
			$query->where('valid_to', '>=', now())->orWhereNull('valid_to');
		});
	}

	public function scopeExpired(Builder $query): Builder
	{
		return $query->whereNotNull('valid_to')->where('valid_to', '<', now());
	}

	public function scopeDraft(Builder $query): Builder
	{
		return $query->whereNull('valid_from');
	}

	public function scopeScheduled(Builder $query): Builder
	{
		return $query->whereNotNull('valid_from')->where('valid_from', '>', now());
	}

	public function getPrefix(): string
	{
		return $this->entity->slug;
	}

	public function getPath(): ?string
	{
		return $this->categories()->first()?->getPath();
	}
}
