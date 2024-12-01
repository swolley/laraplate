<?php

namespace Modules\Editorial\Models;

use Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasCommonObserver;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Author extends Model
{
    use HasCommonObserver, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    protected static function boot()
    {
        parent::boot();

        // static::addGlobalScope('always_with_user', function (Builder $query, $user) {
        //     $query->where('user_id', $user->id);
        // });
    }

    /**
     * @return BelongsTo<User>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
