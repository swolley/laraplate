<?php

namespace Modules\Cms\Models;

use Spatie\Tags\HasTags;
use Parental\HasChildren;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Illuminate\Support\Carbon;
use Modules\Cms\Helpers\HasPath;
use Modules\Cms\Helpers\HasSlug;
use Awobaz\Compoships\Compoships;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Support\Collection;
use Modules\Core\Cache\Searchable;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Helpers\HasVersions;
use Spatie\EloquentSortable\Sortable;
use Modules\Core\Helpers\HasApprovals;
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
class Content extends Model implements HasMedia, Sortable
{
	use SoftDeletes, HasTags, HasValidity, HasLocks, HasOptimisticLocking, HasVersions, HasChildren, SortableTrait, InteractsWithMedia, HasSlug, HasPath, HasValidations, Searchable, Compoships/*, HasApprovals*/ {
		prepareElasticDocument as protected prepareElasticDocumentTrait;
		getRules as protected getRulesTrait;
		Compoships::hasMany insteadof HasChildren;
		Compoships::belongsTo insteadof HasChildren;
	}

	protected $fillable = ['valid_from', 'valid_to', 'preset_id', 'entity_id'/*, 'components'*/];

	protected $with = ['entity', 'authors', 'categories', 'categories.ancestors'];

	protected $hidden = ['preset_id', 'entity_id', 'created_at', 'updated_at', 'deleted_at', 'entity', 'components', 'preset', 'withCaching', 'withoutObjectCaching'];

	protected $childColumn = 'entity_id';

	protected $sortable = [
		'order_column_name' => 'order_column',
		'sort_when_creating' => true,
	];

	protected $attributes = [
		'components' => '{}',
	];

	public static array $childTypes = [];

	// protected $embed = ['components'];

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
		return static::$childTypes;
	}

	public function __construct(array $attributes = [])
	{
		parent::__construct($attributes);

		if (static::class !== Content::class && !$this->entity_id) {
			$entity = Cache::rememberForever((new Entity())->getCacheKey(), fn() => Entity::withoutGlobalScopes()->get())->firstWhere('name', Str::snake(class_basename($this::class)));
			if ($entity) {
				$this->entity()->associate($entity);
				$this->entity_id = $entity?->id;
				$preset = Cache::rememberForever((new Preset())->getCacheKey(), fn() => Preset::withoutGlobalScopes()->get())->firstWhere('entity_id', $entity->id);
				if ($preset) {
					$this->preset()->associate($preset);
					$this->preset_id = $preset?->id;
				}
			}
		}
	}

	#[\Override]
	public function __get($key)
	{
		$components = $this->getComponentsAttribute();
		return array_key_exists($key, $components)
			? data_get($components, $key)
			: parent::__get($key);
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
			}

			if ($content->preset) {
				if ($content->entity_id && $content->entity_id !== $content->preset->entity_id) {
					throw new \UnexpectedValueException("Entity mismatch: {$content->entity->name} is not compatible with {$content->preset->name}");
				}
				$content->entity_id = $content->preset->entity_id;
			}
		});
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

	protected function name(): Attribute
	{
		return Attribute::make(
			get: function () {
				$field = $this->preset?->fields()->select(['name', 'is_slug'])->firstWhere('is_slug', true);
				return $field ? data_get($this->getComponentsAttribute(), $field->name) : '';
			},
		);
	}

	protected function cover(): Attribute
	{
		return Attribute::make(
			get: fn() => $this->getFirstMediaUrl('cover'),
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
		return $this->belongsToMany(Category::class, 'categorizables', ['content_id', 'entity_id'], ['id', 'entity_id'])->using(Categorizable::class)->withTimestamps();
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
		return $this->belongsTo(Preset::class, ['preset_id', 'entity_id'], ['id', 'entity_id'])->withTrashed();
	}

	public function related(?bool $withInverse = false): BelongsToMany
	{
		$relation = $this->belongsToMany(Content::class, 'relatables')->using(Relatable::class)->withTimestamps();
		if ($withInverse) {
			$relation->orWhere(fn($query) => $query->where('related_content_id', $this->id));
		}
		return $relation;
	}

	#endregion

	protected function prepareElasticDocument(): array
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

		return $document;
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
			$fields[/*'values.' . */$field->name] = trim($rule, '|');
		}

		$rules = $this->getRulesTrait();
		$rules[static::DEFAULT_RULE] = array_merge($rules[static::DEFAULT_RULE], [
			// 'values' => 'required',
			...$fields,
			'entity_id' => 'required|exists:entities,id',
			'preset_id' => 'required|exists:presets,id',
		]);
		return $rules;
	}

	public function getPrefix(): string
	{
		return $this->entity->slug;
	}

	public function getPath(): ?string
	{
		return $this->categories()->first()?->getPath();
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

	/**
	 * Publish the content.
	 * @param null|Carbon $valid_from 
	 * @param null|Carbon $valid_to 
	 * @return void 
	 */
	public function publish(?Carbon $valid_from = null, ?Carbon $valid_to = null): void
	{
		$valid_from = $valid_from ?? now();
		if ($valid_to) {
			$min = min($valid_from, $valid_to);
			$max = max($valid_from, $valid_to);
			$valid_from = $min;
			$valid_to = $max;
		}

		$this->valid_from = $valid_from;
		$this->valid_to = $valid_to;
	}

	/**
	 * Unpublish the content.
	 * @return void 
	 */
	public function unpublish(): void
	{
		$this->valid_from = null;
		$this->valid_to = null;
	}
}
