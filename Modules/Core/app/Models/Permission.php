<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Modules\Core\Cache\HasCache;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Helpers\HasValidations;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Database\Factories\PermissionFactory;
use Spatie\Permission\Models\Permission as ModelsPermission;

/**
 * @mixin IdeHelperPermission
 */
class Permission extends ModelsPermission
{
    use HasValidations, SoftDeletes, HasCache {
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
        'guard_name',
    ];

    /**
     * @var string[]
     *
     * @psalm-suppress NonInvariantPropertyType
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $hidden = [
        'pivot',
    ];

    protected $append = [
        'action',
    ];

    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->guarded = array_merge($this->guarded ?? [], [
            'connection_name',
            'table_name',
        ]);
    }

    protected static function newFactory(): PermissionFactory
    {
        return PermissionFactory::new();
    }

    protected function getActionAttribute(): ?ActionEnum
    {
        if (!isset($this->name)) {
            return null;
        }
        $splitted = explode('.', $this->name);

        return ActionEnum::tryFrom(array_pop($splitted));
    }

    public function getRules()
    {
        return $this->getRulesTrait() + [
            static::DEFAULT_RULE => [
                'name' => 'string|max:255|required|regex:/^\\w+\\.\\w+\\.\\w+$/',
                'guard_name' => 'string|max:255',
                'description' => 'string|max:255|nullable',
            ],
        ];
    }
}
