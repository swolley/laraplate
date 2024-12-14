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
    use HasFactory, HasLocks, HasValidations, SoftDeletes, HasVersions;

    // region [ATTRIBUTES]

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
        ];
    }

    // endregion

    public function __construct($attributes = [])
    {
        $this->rules[static::DEFAULT_RULE] = [
            'name' => 'string|required|max:255',
            'command' => 'string|required|max:255',
            'parameters' => 'json|required',
            'schedule' => ['required', new CronExpressionRule],
            'description' => 'string|max:255|nullable',
            'is_active' => 'boolean|required',
        ];

        parent::__construct($attributes);
    }

    protected static function newFactory(): CronJobFactory
    {
        return CronJobFactory::new();
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function (CronJob $cronJob): void {
            Cache::forget($cronJob->getTable());
        });
        static::deleted(function (CronJob $cronJob): void {
            Cache::forget($cronJob->getTable());
        });
    }
}
