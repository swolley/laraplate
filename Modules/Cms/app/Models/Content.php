<?php

namespace Modules\Cms\Models;

use Parental\HasChildren;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Modules\Cms\Helpers\HasPath;
use Modules\Cms\Helpers\HasSlug;
use Modules\Cms\Helpers\HasTags;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Support\Collection;
use Modules\Core\Cache\Searchable;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Helpers\HasVersions;
use Spatie\EloquentSortable\Sortable;
use Modules\Core\Helpers\HasApprovals;
use Modules\Cms\Models\Pivot\Relatable;
use Modules\Cms\Models\Pivot\Authorable;
use Modules\Core\Helpers\HasValidations;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Locking\Traits\HasLocks;
use Spatie\EloquentSortable\SortableTrait;
use Modules\Cms\Models\Pivot\Categorizable;
use Modules\Core\Overrides\ComposhipsModel;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Locking\HasOptimisticLocking;
use Spatie\MediaLibrary\Conversions\Conversion;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Modules\Cms\Database\Factories\ContentFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperContent
 */
class Content extends ComposhipsModel implements HasMedia, Sortable
{
	use HasFactory, 
		SoftDeletes, 
		HasTags, 
		HasValidity, 
		HasLocks, 
		HasOptimisticLocking, 
		HasVersions, 
		HasChildren, 
		SortableTrait, 
		InteractsWithMedia, 
		HasSlug, 
		HasPath, 
		HasValidations, 
		Searchable
		/*, HasApprovals*/ {
		prepareElasticDocument as protected prepareElasticDocumentTrait;
		getRules as protected getRulesTrait;
		HasChildren::hasMany as protected hasChildrenHasMany;
		HasChildren::belongsTo as protected hasChildrenBelongsTo;
		HasChildren::belongsToMany as protected hasChildrenBelongsToMany;
	}

	protected $fillable = [
		'valid_from', 
		'valid_to', 
		'preset_id',
		'entity_id',
		'components',
	];

	protected $with = [
		'entity', 
		'authors', 
		'categories', 
		'catego	ries.ancestors', 
		'media',
	];

	protected $hidden = [
		'preset_id', 
		'entity_id', 
		'created_at', 
		'updated_at', 
		'deleted_at', 
		'entity', 
		'components', 
		'preset', 
		'withCaching', 
		'withoutObjectCaching',
	];

	protected $childColumn = 'entity_id';

	protected $sortable = [
		'order_column_name' => 'order_column',
		'sort_when_creating' => true,
	];

	protected $attributes = [
		'components' => '{}',
	];

	protected $appends = [
		'cover', 
		'path',
	];

	public static array $childTypes = [];

	public static ?Collection $all_presets = null;

	// TODO: capire come estrarre i contenuti dinamici per l'embedding
	// protected $embed = ['components'];

	protected function casts(): array
	{
		return [
			'components' => 'json',
			'preset_id' => 'integer',
			'entity_id' => 'integer',
			'created_at' => 'immutable_datetime',
			'updated_at' => 'datetime',
			'deleted_at' => 'datetime',
		];
	}

	protected function getChildTypes(): array
	{
		if (empty(static::$childTypes)) {
			static::resolveChildTypes();
		}
		return static::$childTypes;
	}

	/**
	 * 
	 * @param Collection<Entity> $entities 
	 * @return void 
	 */
	protected static function resolveChildTypes(?Collection $entities = null): void
	{
		static::$childTypes = [];
		static::$all_presets = null;

		if ($entities === null) {
			$entities = Entity::query()->withoutGlobalScopes()->get();
			Cache::forever((new Entity())->getCacheKey(), $entities);
		}

		foreach ($entities as $entity) {
			$class_name = Str::studly($entity->name);
			$full_class_name = 'Modules\\Cms\\Models\\Contents\\' . $class_name;
			if (!class_exists($full_class_name)) {
				$class_definition = file_get_contents(module_path('Cms', 'stubs/content.stub'));
				$class_definition = str_replace(['$CLASS$', '<?php'], [$class_name, ''], $class_definition);
				eval($class_definition);
			}
			static::$childTypes[$entity->id] = $full_class_name;
		}
	}

	public static function makeFromEntity(Entity|string|int $entity): static
	{
		if (static::$all_presets === null) {
			static::resolveChildTypes();
		}

		if (is_int($entity)) {
			$entity_id = array_key_exists($entity, static::$childTypes) ? $entity : null;
		} else if (is_string($entity)) {
			$entity_id = array_key_first(array_filter(static::$childTypes, fn($class) => Str::endsWith($class, '\\' . Str::studly($entity))));
		} else if (is_object($entity) && array_key_exists($entity->id, static::$childTypes)) {
			$entity_id = $entity->id;
		}
		if (!$entity_id) {
			throw new \InvalidArgumentException("Invalid entity: " . $entity);
		}

		if (static::$all_presets === null) {
			static::$all_presets = Cache::rememberForever(
				(new Preset())->getCacheKey(),
				fn() => Preset::withoutGlobalScopes()->get()
			);
		}
		$preset_id = static::$all_presets->firstWhere('entity_id', $entity_id)?->id;
		if (!$preset_id) {
			throw new \InvalidArgumentException("No preset found for entity: " . $entity);
		}

		return new static::$childTypes[$entity_id](['preset_id' => $preset_id, 'entity_id' => $entity_id]);
	}


	#[\Override]
	public function toArray(): array
	{
		$content = parent::toArray();
		if (isset($content['components'])) {
			$components = $content['components'];
			unset($content['components']);
			$content = array_merge($content, $components);
		} else {
			$content = array_merge($content, $this->getComponentsAttribute());
		}

		return $content;
	}

	#[\Override]
	protected static function boot()
	{
		parent::boot();

		static::addGlobalScope('multi_ordered', function (Builder $builder) {
			$builder->ordered('asc')->orderBy('valid_from', 'desc')->orderBy('contents.created_at', 'desc');
		});

		static::saving(function ($content) {
			if ($content->preset) {
				$content->preset_id = $content->preset->id;
				if ($content->entity_id && $content->entity_id !== $content->preset->entity_id) {
					throw new \UnexpectedValueException("Entity mismatch: {$content->entity->name} is not compatible with {$content->preset->name}");
				}
				$content->entity_id = $content->preset->entity_id;
			}
		});
	}

	protected static function newFactory(): ContentFactory
    {
        return ContentFactory::new();
    }

	#region Scopes

	protected function scopeForEntity(Builder $query, Entity $entity): Builder
	{
		return $query->where('entity_id', $entity->id);
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

	#endregion

	#region Attributes

	#[\Override]
	public function __get($key)
	{
		if ($this->hasAttribute($key) || method_exists(self::class, $key)) {
			return parent::__get($key);
		}

		return data_get($this->getComponentsAttribute(), $key);
	}

	#[\Override]
	public function __set($key, $value)
	{
		$components = $this->getComponentsAttribute();
		if (array_key_exists($key, $components)) {
			$components[$key] = $value;
			$this->setComponentsAttribute($components);
			return;
		}

		parent::__set($key, $value);

		if ($key === 'preset_id' && $value) {
			$this->entity_id = $this->preset?->entity_id;
		}
	}

	protected function slugFields(): array
	{
		return [$this->preset?->fields()->select(['name', 'is_slug'])->firstWhere('is_slug', true)?->name];
	}

	protected function cover(): Attribute
	{
		return Attribute::make(
			get: fn() => $this->getFirstMedia('cover'),
			set: fn($value) => $this->addMedia($value)->toMediaCollection('cover'),
		);
	}

	protected function getComponentsAttribute(): array
	{
		return $this->mergeComponentsValues(json_decode($this->attributes['components'], true));
	}

	protected function setComponentsAttribute(array $components): void
	{
		$this->attributes['components'] = json_encode($this->mergeComponentsValues($components));
	}

	private function mergeComponentsValues(array $components): array
	{
		return $this->fields()->mapWithKeys(function (Field $field) use ($components) {
			return [$field->name => data_get($components, $field->name) ?? $field->default];
		})->toArray();
	}

	#endregion

	#region Relations

	/**
	 * The fields that belong to the content.
	 * @return Collection<Field>
	 */
	private function fields(): Collection
	{
		return $this->preset?->fields ?? collect();
	}

	/**
	 * The entity that belongs to the content.
	 * @return BelongsTo<Entity>
	 */
	public function entity(): BelongsTo
	{
		return $this->belongsTo(Entity::class)->withTrashed();
	}

	/**
	 * The folders that belong to the content.
	 * @return BelongsToMany<Category>
	 */
	public function categories(): BelongsToMany
	{
		return parent::belongsToMany(
			Category::class,
			'categorizables',
			['content_id', 'entity_id'],
			['category_id', 'entity_id'],
			['id', 'entity_id'],
			['id', 'entity_id']
		)->using(Categorizable::class)->withTimestamps();
	}

	/**
	 * The location that belongs to the content.
	 * @return BelongsTo<Location>
	 */
	public function location(): BelongsTo
	{
		return $this->belongsTo(Location::class, 'content_id', 'location_id', 'id')->withTrashed();
	}

	/**
	 * The authors that belong to the content.
	 * @return BelongsToMany<Author>
	 */
	public function authors(): BelongsToMany
	{
		return $this->belongsToMany(Author::class, 'authorables')
			->using(Authorable::class)
			->withTimestamps();
	}

	/**
	 * The preset that belongs to the content.
	 * @return BelongsTo<Preset>
	 */
	public function preset(): BelongsTo
	{
		return $this->belongsTo(Preset::class, ['preset_id', 'entity_id'], ['id', 'entity_id'])->withTrashed();
	}

	/**
	 * The related contents that belong to the content.
	 * @param bool|null $withInverse 
	 * @return BelongsToMany<Content>
	 */
	public function related(?bool $withInverse = false): BelongsToMany
	{
		// $relation = $this->hasChildrenBelongsToMany(Content::class, 'relatables')->using(Relatable::class)->withTimestamps();
		$relation = $this->belongsToMany(Content::class, 'relatables')->using(Relatable::class)->withTimestamps();
		if ($withInverse) {
			$relation->orWhere(fn($query) => $query->where('related_content_id', $this->id));
		}
		return $relation;
	}

	#endregion

	public function prepareElasticDocument(): array
	{
		$document = $this->prepareElasticDocumentTrait();
		$document['authors'] = $this->authors->pluck('name')->toArray();
		$document['authors_id'] = $this->authors->pluck('id')->toArray();
		$document['preset'] = $this->preset->name;
		$document['entity'] = $this->entity->name;
		$document['categories'] = $this->categories->pluck('name')->toArray();
		$document['categories_id'] = $this->categories->pluck('id')->toArray();
		$document['tags'] = $this->tags->pluck('name')->toArray();
		$document['tags_id'] = $this->tags->pluck('id')->toArray();
		$document['location'] = $this->location->name;
		$document['location_id'] = $this->location->id;
		$document['slug'] = $this->slug;

		return $document;
	}

	public function registerMediaCollections(): void
	{
		$this->addMediaCollection('cover')->singleFile();
		$this->addMediaCollection('images');
		$this->addMediaCollection('videos');
		$this->addMediaCollection('audios');
		$this->addMediaCollection('files');
	}


	public function registerMediaConversions(?Media $media = null): void
	{
		self::commonThumbSizes(
			$this->addMediaConversion('thumb')->performOnCollections('images', 'cover')
		);
		self::commonThumbSizes(
			$this->addMediaConversion('video_thumb')->performOnCollections('videos')->extractVideoFrameAtSecond(2)
		);
	}

	private static function commonThumbSizes(Conversion $conversion): void
	{
		$conversion->width(300)
			->height(300)
			->sharpen(10)
			->fit(Fit::Fill, 300, 300);
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
			$fields[$field->name] = trim($rule, '|');
		}

		$rules = $this->getRulesTrait();
		$rules[static::DEFAULT_RULE] = array_merge($rules[static::DEFAULT_RULE], [
			...$fields,
			'entity_id' => 'required|exists:entities,id',
			'preset_id' => 'required|exists:presets,id',
		]);
		return $rules;
	}

	public function getPathPrefix(): string
	{
		return $this->entity?->slug ?? '';
	}

	#[\Override]
	public function getPath(): ?string
	{
		return $this->categories->first()?->getPath();
	}
}
