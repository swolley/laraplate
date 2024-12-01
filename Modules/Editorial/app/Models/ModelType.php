<?php

namespace Modules\Editorial\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasCommonObserver;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Editorial\Database\Factories\ModelTypeFactory;

class ModelType extends Model
{
    use HasFactory, HasCommonObserver, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): ModelTypeFactory
    // {
    //     // return ModelTypeFactory::new();
    // }

    /**
     * @return BelongsTo<Newspaper>
     */
    public function newspaper(): BelongsTo
    {
        return $this->belongsTo(Newspaper::class);
    }

    /**
     * @return BelongsTo<Template>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
