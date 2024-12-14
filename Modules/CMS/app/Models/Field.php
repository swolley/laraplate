<?php

namespace Modules\CMS\Models;

use Modules\CMS\Casts\FieldType;
use Modules\Core\Helpers\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Modules\CMS\Models\Pivot\Fieldable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperField
 */
class Field extends Model
{
    use HasFactory, SoftDeletes, HasVersions;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'type', 'options', 'is_active'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'options' => 'json',
            'is_active' => 'boolean',
            'type' => FieldType::class,
            'created_at' => 'immultable_datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // protected static function boot()
    // {
    //     parent::boot();

    //     self::addGlobalScope('api', function (Builder $builder) {
    //         if (request()?->is('api/*')) {
    //             $builder->where('is_active', true);
    //         }
    //     });
    // }

    public function presets(): BelongsToMany
    {
        return $this->belongsToMany(Preset::class, 'fieldables')->using(Fieldable::class)->withTimestamps();
    }
}
