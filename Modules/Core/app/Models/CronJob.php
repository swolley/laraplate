<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Helpers\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Locking\Traits\HasLocks;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Database\Factories\CronJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Casts\CronExpression as CronExpressionCast;
use Modules\Core\Rules\CronExpression as CronExpressionRule;

/**
 * @mixin IdeHelperCronJob
 */
class CronJob extends Model
{
    use HasFactory, HasLocks, SoftDeletes, HasVersions, HasValidations {
        getRules as protected getRulesTrait;
    }

    /**
     * @var string[]
     *
     * @psalm-suppress NonInvariantPropertyType
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $fillable = [
        'name',
        'command',
        'parameters',
        'schedule',
        'description',
        'is_active',
    ];

    protected $attributes = [
        'parameters' => '{}',
    ];

    protected function casts()
    {
        return [
            'name' => 'string',
            'command' => 'string',
            'parameters' => 'json',
            'schedule' => CronExpressionCast::class,
            'description' => 'string',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CronJobFactory
    {
        return CronJobFactory::new();
    }

    protected static function booted()
    {
        static::saved(function (CronJob $cronJob): void {
            Cache::forget($cronJob->getTable());
        });
        static::deleted(function (CronJob $cronJob): void {
            Cache::forget($cronJob->getTable());
        });
    }

    public function getRules()
    {
        $rules = $this->getRulesTrait();
        $rules[static::DEFAULT_RULE] = array_merge($rules[static::DEFAULT_RULE], [
            'command' => ['required', 'string', 'max:255'],
            'parameters' => ['required', 'json'],
            'schedule' => ['required', new CronExpressionRule],
            'description' => ['string', 'max:255', 'nullable'],
            'is_active' => ['boolean', 'required'],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255', 'unique:cron_jobs,name'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255', 'unique:cron_jobs,name,' . $this->id],
        ]);
        return $rules;
    }
}
