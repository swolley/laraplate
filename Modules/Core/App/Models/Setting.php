<?php

declare(strict_types=1);

namespace Modules\Core\App\Models;

use Illuminate\Validation\Rules\Enum;
use Modules\Core\App\Helpers\HasCache;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\App\Helpers\HasApprovals;
use Modules\Core\App\Casts\SettingTypeEnum;
use Modules\Core\App\Helpers\HasValidations;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\App\Helpers\HasCommonObserver;
use Modules\Core\Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperSetting
 */
class Setting extends Model
{
    use HasApprovals, HasCommonObserver, HasFactory, HasValidations, SoftDeletes, HasCache;

    /**
     * @var string[]
     *
     * @psalm-suppress NonInvariantPropertyType
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $fillable = [
        'name',
        'value',
        'encrypted',
        'choices',
        'type',
        'group_name',
        'description',
    ];

    protected $attributes = [
        'encrypted' => false,
        'type' => 'string',
        'group_name' => 'base',
    ];

    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->rules[static::DEFAULT_RULE] = [
            'name' => 'string|required|max:50',
            'encrypted' => 'boolean|required',
            'choices' => 'sometimes|nullable',
            'choices.*' => 'filled',
            'type' => ['required', new Enum(SettingTypeEnum::class)],
            'group_name' => 'string|required|max:50',
            'description' => 'string|max:255|nullable',
            'locked_at' => 'date|nullable',
        ];
    }

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }

    protected function casts()
    {
        return [
            'value' => 'json',
            'encrypted' => 'boolean',
            'choices' => 'array',
            'type' => SettingTypeEnum::class,
        ];
    }

    protected function requiresApprovalWhen($modifications): bool
    {
        return !empty(array_intersect(
            array_filter($this->getFillable(), fn($field) => $field !== 'description'),
            array_keys($modifications),
        ));
        // return !empty($modifications);
    }
}
