<?php

namespace Modules\Editorial\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasCommonObserver;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Editorial\Database\Factories\NewspaperFactory;

class Newspaper extends Model
{
    use HasFactory, HasCommonObserver, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): NewspaperFactory
    // {
    //     // return NewspaperFactory::new();
    // }

    /**
     * @return HasMany<Folder>
     */
    private function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /**
     * @return HasMany<Folder>
     */
    public function sections(): HasMany
    {
        return $this->folders()->whereEntity('stories');
    }

    /**
     * @return HasMany<Folder>
     */
    public function categories(): HasMany
    {
        return $this->folders()->whereEntity('events');
    }

    /**
     * @return HasMany<ModelType>
     */
    public function models(): HasMany
    {
        return $this->hasMany(ModelType::class)->orWhereNull('newspaper_id');
    }
}
