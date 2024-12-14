<?php

namespace Modules\CMS\Models;

use Modules\CMS\Helpers\HasSlug;
use Modules\Core\Cache\HasCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

// use Modules\CMS\Database\Factories\EntityFactory;

/**
 * @mixin IdeHelperEntity
 */
class Entity extends Model
{
    use HasFactory, SoftDeletes, HasCache, HasSlug;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'slug'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'immultable_datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // protected static function newFactory(): EntityFactory
    // {
    //     // return EntityFactory::new();
    // }

    protected static function boot()
    {
        parent::boot();

        self::addGlobalScope('api', function (Builder $builder) {
            if (request()?->is('api/*')) {
                $builder->where('is_active', true);
            }
        });
    }

    public function presets(): HasMany
    {
        return $this->hasMany(Preset::class);
    }
}
