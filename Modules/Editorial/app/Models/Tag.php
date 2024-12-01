<?php

namespace Modules\Editorial\Models;

use Modules\Core\Helpers\HasCommonObserver;

class Tag extends \Spatie\Tags\Tag
{
    use HasCommonObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): TagFactory
    // {
    //     // return TagFactory::new();
    // }
}
