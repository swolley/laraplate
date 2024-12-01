<?php

namespace Modules\Editorial\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasCommonObserver;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
// use Modules\Editorial\Database\Factories\SectionFactory;

class Folder extends Model
{
    use HasFactory, HasRecursiveRelationships, HasCommonObserver, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): SectionFactory
    // {
    //     // return SectionFactory::new();
    // }

    /**
     * @return BelongsTo<Newspaper>
     */
    public function newspaper(): BelongsTo
    {
        return $this->belongsTo(Newspaper::class);
    }
}
