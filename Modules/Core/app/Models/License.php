<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Modules\Core\Helpers\HasValidity;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Core\Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperLicense
 */
class License extends Model
{
    use HasFactory, HasUuids, HasValidity, HasValidations {
        getRules as protected getRulesTrait;
    }

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

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

    public function getRules()
    {
        $rules = $this->getRulesTrait();
        $rules[static::DEFAULT_RULE] = array_merge($rules[static::DEFAULT_RULE], [
            'valid_from' => ['date'],
            'valid_to' => ['nullable', 'date'],
        ]);
        return $rules;
    }
}
