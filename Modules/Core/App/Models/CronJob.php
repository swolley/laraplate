<?php

declare(strict_types=1);

namespace Modules\Core\App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\App\Helpers\HasValidations;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\App\Helpers\HasCommonObserver;
use Modules\Core\Database\Factories\CronJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\App\Casts\CronExpression as CronExpressionCast;
use Modules\Core\App\Rules\CronExpression as CronExpressionRule;

class CronJob extends Model
{
    use HasCommonObserver, HasFactory, HasLocks, HasValidations, SoftDeletes;

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
            'schedule' => ['required', CronExpressionRule::class],
            'description' => 'string|max:255|nullable',
            'is_active' => 'boolean|required',
        ];

        parent::__construct($attributes);
    }

    protected static function newFactory(): CronJobFactory
    {
        return CronJobFactory::new();
    }
}
