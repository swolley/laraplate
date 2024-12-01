<?php

namespace Modules\Editorial\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasCommonObserver;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Editorial\Database\Factories\TemplateFactory;

class Template extends Model
{
    use HasFactory, HasCommonObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): TemplateFactory
    // {
    //     // return TemplateFactory::new();
    // }

    /**
     * @return HasMany<ModelType>
     */
    public function models(): HasMany
    {
        return $this->hasMany(ModelType::class);
    }
}
