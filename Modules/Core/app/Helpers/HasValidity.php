<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use InvalidArgumentException;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Exceptions\InvalidFormatException;

trait HasValidity
{
    protected static $valid_from_column = 'valid_from';

    protected static $valid_to_column = 'valid_to';

    protected function casts()
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public static function validFromKey(): string
    {
        return static::$valid_from_column;
    }

    public static function validToKey(): string
    {
        return static::$valid_to_column;
    }

    protected static function bootHasValidity(): void
    {
        static::addGlobalScope('valid', function (Builder $query): void {
            static::withValidityFilter($query, Carbon::today());
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    protected static function withValidityFilter(Builder $query, Carbon $date): Builder
    {
        return $query->where(static::$valid_from_column, '<=', $date)->where(function ($q) use ($date): void {
            $q->where(static::$valid_to_column, '>=', $date)->orWhereNull(static::$valid_to_column);
        });
    }

    protected function scopeExpired(Builder $query)
    {
        $query->withoutGlobalScopes()->whereNotNull('valid_to')->where('valid_to', '<', now());
    }

    protected function scopeExpiredAt(Builder $query, Carbon $date)
    {
        $query->expired()->validAt($date);
    }

    /**
     *
     * @throws InvalidArgumentException
     */
    public function scopeValidAt(Builder $query, Carbon $date): void
    {
        static::withValidityFilter($query, $date);
    }

    /**
     * @throws InvalidFormatException
     */
    public function isValid(?Carbon $date = null): bool
    {
        if (!$date) {
            $date = Carbon::today();
        }

        return $date->gte($this->{static::$valid_from_column}) && (!$this->{static::$valid_to_column} || $date->lte($this->{static::$valid_to_column}));
    }
}
