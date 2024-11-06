<?php

namespace Modules\Core\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

// use Modules\Core\Database\Factories\ModelEmbeddingFactory;

/**
 * @mixin IdeHelperModelEmbedding
 */
class ModelEmbedding extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "embedding",
    ];

    protected $casts = [
        "embedding" => "json",
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
