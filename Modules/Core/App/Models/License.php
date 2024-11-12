<?php

declare(strict_types=1);

namespace Modules\Core\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\App\Helpers\HasValidity;
use Modules\Core\App\Helpers\HasValidations;
use Modules\Core\App\Helpers\HasCommonObserver;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Core\Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperLicense
 */
class License extends Model
{
    use HasFactory, HasUuids, HasCommonObserver, HasValidity, HasValidations;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    public function __construct($attributes = [])
    {
        $this->rules[static::DEFAULT_RULE] = [
            'valid_from' => 'date',
            'valid_to' => 'nullable|date',
        ];

        parent::__construct($attributes);
    }

    protected static function newFactory(): LicenseFactory
    {
        return LicenseFactory::new();
    }

    protected function scopeFree(Builder $query)
    {
        $query->doesntHave('user');
    }

    protected function scopeOccupied(Builder $query)
    {
        $query->has('user');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
