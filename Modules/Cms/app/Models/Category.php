<?php

namespace Modules\Cms\Models;

use Modules\Cms\Helpers\HasSlug;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\HasApprovals;
use Illuminate\Database\Eloquent\Model;
use Spatie\EloquentSortable\SortableTrait;
use Modules\Cms\Models\Pivot\Categorizable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * @mixin IdeHelperCategory
 */
class Category extends Model
{
    use HasFactory, HasRecursiveRelationships, SoftDeletes, HasValidity, HasApprovals, HasVersions, SortableTrait, HasSlug;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['entity_id', 'parent_id', 'name', 'slug', 'description', 'model_type_id', 'order', 'persistence', 'logo', 'logo_full', 'is_active'];

    protected $hidden = ['entity_id', 'parent_id', 'model_type_id', 'order', 'persistence', 'is_active', 'created_at', 'updated_at', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'model_type_id' => 'integer',
            'order' => 'integer',
            'persistence' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'categorizables')->using(Categorizable::class)->withTimestamps();
    }
}
