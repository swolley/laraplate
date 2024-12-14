<?php

namespace Modules\CMS\Models;

use Spatie\Tags\HasTags;
use Parental\HasChildren;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Modules\Core\Cache\Searchable;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Helpers\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Modules\CMS\Models\Pivot\Authorable;
use Modules\Core\Locking\Traits\HasLocks;
use Spatie\EloquentSortable\SortableTrait;
use Modules\CMS\Models\Pivot\Categorizable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperContent
 */
class Content extends Model
{
	use SoftDeletes, HasTags, HasValidity, HasLocks, HasVersions, HasChildren, SortableTrait, Searchable {
		prepareElasticDocument as protected prepareElasticDocumentTrait;
	}

	// protected $fillable = ['items'];

	protected $hidden = ['author_id', 'model_type_id', 'entity_id', 'created_at', 'updated_at', 'deleted_at', 'entity'];

	protected $childColumn = 'entity_id';

	protected function casts(): array
	{
		return [
			'items' => 'json',
			'author_id' => 'integer',
			'model_type_id' => 'integer',
			'created_at' => 'immultable_datetime',
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

		$this->items = $attributes['items'] ?? new \stdClass;

		if (static::class !== Content::class && !$this->entity_id) {
			$this->entity()->associate(static::getAvailableEntities()->firstWhere('name', Str::snake(class_basename($this::class))));
		}
	}

	#[\Override]
	protected static function boot()
	{
		parent::boot();

		static::addGlobalScope('always_with_entity', function (Builder $builder) {
			$builder->with('entity');
		});
	}

	/**
	 * 
	 * @return string
	 */
	public function getNameAttribute(): string
	{
		return $this->components()->firstWhere('is_slug', true)->value;
	}

	public function entity(): BelongsTo
	{
		return $this->belongsTo(Entity::class);
	}

	/**
	 * The folders that belong to the content.
	 */
	public function categories(): BelongsToMany
	{
		return $this->belongsToMany(Category::class, 'categorizables')->using(Categorizable::class)->withTimestamps();
	}

	/**
	 * The author that belongs to the content.
	 */
	public function authors(): BelongsToMany
	{
		return $this->belongsToMany(Author::class)->using(Authorable::class)->withTimestamps();
	}

	/**
	 * @return BelongsTo<Preset>
	 */
	public function preset(): BelongsTo
	{
		return $this->belongsTo(Preset::class);
	}

	// protected function __get($key)
	// {
	// 	if (property_exists($this->items, $key)) {
	// 		return $this->items->{$key};
	// 	}

	// 	return parent::__get($key);
	// }

	// protected function __set($key, $value)
	// {
	// 	if (property_exists($this->items, $key)) {
	// 		return $this->items->{$key};
	// 	}

	// 	parent::__set($key, $value);
	// }

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

	// Magic getter for items attributes
	#[\Override]
	public function __get($key)
	{
		// $entity = new User();
		// if (in_array($key, $entity->getFillable())) {
		// 	return $this->user ? $this->user->{$key} : null;
		// }
		return parent::__get($key);
	}

	// Magic setter for items attributes
	#[\Override]
	public function __set($key, $value)
	{
		// $session_user = Auth::user();
		// $entity = new User();
		// $table = $entity->getTable();
		// $user_can_insert = $session_user && $session_user->can("$table.create");
		// $user_can_update = $session_user && $session_user->can("$table.update");
		// if (in_array($key, $entity->getFillable()) && ($user_can_insert || $user_can_update)) {
		// 	if (!$this->user && !$user_can_insert) {
		// 		throw new UnauthorizedException("User cannot insert $entity");
		// 	}

		// 	if (!$this->user && !isset($this->tempUser) && $user_can_insert) {
		// 		$this->tempUser = new User();
		// 		$this->tempUser->{$key} = $value;
		// 	} else if ($user_can_update) {
		// 		$this->user->{$key} = $value;
		// 	}
		// 	return;
		// }
		parent::__set($key, $value);
	}

	// Save method to handle user creation/updating
	#[\Override]
	public function save(array $options = [])
	{
		// if (isset($this->tempUser) && $this->tempUser->isDirty()) {
		// 	$this->tempUser->save();
		// 	$this->user_id = $this->tempUser->id;
		// 	unset($this->tempUser);
		// 	$this->load('user');
		// } else if ($this->user && $this->user->isDirty()) {
		// 	$this->user->save();
		// }
		parent::save($options);
	}

	#[\Override]
	public function toArray(): array
	{
		$content = parent::toArray();
		$user = $this->user ? $this->user->toArray() : null;
		return $user ? array_merge($content, $user) : $content;
	}
}
