<?php

namespace Modules\CMS\Models;

use Spatie\Tags\Tag as BaseTag;
// use Modules\CMS\Helpers\HasSlug;

/**
 * @mixin IdeHelperTag
 */
class Tag extends BaseTag
{
    // use HasSlug;

    public array $translatable = [];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'slug', 'type', 'order_column'];

    protected $hidden = ['order_column', 'created_at', 'updated_at', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'order_column' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // protected static function newFactory(): TagFactory
    // {
    //     // return TagFactory::new();
    // }
}
