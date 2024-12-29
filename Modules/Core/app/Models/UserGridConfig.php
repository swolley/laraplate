<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Modules\Core\Cache\HasCache;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidations;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperUserGridConfig
 */
class UserGridConfig extends Model
{
    use HasFactory, HasValidations, HasCache {
        getRules as protected getRulesTrait;
    }

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
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getRules()
    {
        $rules = $this->getRulesTrait();
        $rules[static::DEFAULT_RULE] = array_merge($rules[static::DEFAULT_RULE], [
            'user_id' => ['integer', 'exists:users,id'],
            'grid_name' => ['required', 'max:255'],
            'layout_name' => ['required', 'max:255'],
            'is_public' => ['boolean', 'required'],
            'config' => ['required'],
        ]);
        return $rules;
    }
}
