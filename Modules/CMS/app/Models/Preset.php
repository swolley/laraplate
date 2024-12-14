<?php

namespace Modules\CMS\Models;

use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\HasApprovals;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Builder;
use Modules\CMS\Models\Pivot\Fieldable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// use Modules\CMS\Database\Factories\ModelTypeFactory;

/**
 * @mixin IdeHelperPreset
 */
class Preset extends Model
{
    use HasFactory, SoftDeletes, HasApprovals, HasVersions;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['entity_id', 'name', 'is_active', 'template_id'];

    protected $hidden = ['entity_id', 'template_id', 'is_active', 'created_at', 'updated_at', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'template_id' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'immultable_datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // protected static function newFactory(): ModelTypeFactory
    // {
    //     // return ModelTypeFactory::new();
    // }

    // protected static function boot()
    // {
    //     parent::boot();

    //     self::addGlobalScope('api', function (Builder $builder) {
    //         if (request()?->is('api/*')) {
    //             $builder->where('is_active', true);
    //         }
    //     });
    // }

    /**
     * @return BelongsTo<Template>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, 'fieldables')->using(Fieldable::class)->withTimestamps();
    }
}
