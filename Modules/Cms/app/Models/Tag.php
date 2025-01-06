<?php

namespace Modules\Cms\Models;

use Spatie\Tags\Tag as BaseTag;
use Modules\Cms\Helpers\HasPath;
use Modules\Core\Helpers\HasValidations;
use Modules\CMS\Database\Factories\TagFactory;

/**
 * @mixin IdeHelperTag
 */
class Tag extends BaseTag
{
    use HasValidations, HasPath {
        getRules as protected getRulesTrait;
    }

    public array $translatable = [];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'order_column',
    ];

    protected $hidden = [
        'order_column',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'order_column' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
    }

    public function getRules(): array
    {
        $rules = $this->getRulesTrait();
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255', 'unique:tags,name'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255', 'unique:tags,name,' . $this->id],
        ]);
        return $rules;
    }

    public function getPath(): ?string
    {
        return null;
    }
}
