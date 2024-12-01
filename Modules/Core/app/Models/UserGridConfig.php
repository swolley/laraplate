<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Modules\Core\Helpers\HasCache;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasCommonObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperUserGridConfig
 */
class UserGridConfig extends Model
{
    use HasFactory, HasCommonObserver, HasValidations, HasCache;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'grid_name',
        'layout_name',
        'is_public',
        'config',
    ];

    public function __construct(array $attributes = [])
    {
        $this->rules[static::DEFAULT_RULE] = [
            'user_id' => 'integer|exists:users,id',
            'grid_name' => 'required|max:255',
            'layout_name' => 'required|max:255',
            'is_public' => 'boolean|required',
            'config' => 'required',
        ];

        parent::__construct($attributes);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(user_class());
    }

    protected function casts()
    {
        return [
            'user_id' => 'integer',
            'is_public' => 'boolean',
            'config' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
