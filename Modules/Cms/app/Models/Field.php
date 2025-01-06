<?php

namespace Modules\Cms\Models;

use Illuminate\Validation\Rule;
use Modules\Cms\Casts\FieldType;
use Modules\Cms\Casts\ObjectCast;
use Modules\Core\Helpers\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Modules\Cms\Models\Pivot\Fieldable;
use Modules\Core\Helpers\HasValidations;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read object $options
 * @mixin IdeHelperField
 */
class Field extends Model
{
    use HasFactory, SoftDeletes, HasVersions, HasValidations {
        getRules as protected getRulesTrait;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'type',
        'options',
        'is_active',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'options' => ObjectCast::class,
            'is_active' => 'boolean',
            'type' => FieldType::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // protected static function boot()
    // {
    //     parent::boot();

    //     self::addGlobalScope('api', function (Builder $builder) {
    //         if (request()?->is('api/*')) {
    //             $builder->where('is_active', true);
    //         }
    //     });
    // }

    public function presets(): BelongsToMany
    {
        return $this->belongsToMany(Preset::class, 'fieldables')->using(Fieldable::class)->withTimestamps()->withPivot(['order_column', 'is_required', 'default']);
    }

    public function __get($key)
    {
        if (isset($this->pivot) && isset($this->pivot->{$key})) {
            return $this->pivot->{$key};
        }

        return parent::__get($key);
    }

    #[\Override]
    public function __set($key, $value)
    {
        // if (array_key_exists($key, $this->attributes)) {
        //     parent::__set($key, $value);
        //     return;
        // }
        if (isset($this->pivot) && array_key_exists($key, $this->pivot->getAttributes())) {
            data_set($this->pivot, $key, $value);
            return;
        }
        parent::__set($key, $value);
    }

    #[\Override]
    public function toArray(): array
    {
        $field = parent::toArray();
        if (isset($field['pivot'])) {
            $pivot = $field['pivot'];
            unset($field['pivot']);
            $field = array_merge($field, $pivot);
        } else if (isset($this->pivot)) {
            $field = array_merge($field, $this->pivot->toArray());
        }

        return $field;
    }

    public function getRules(): array
    {
        $rules = $this->getRulesTrait();
        $rules[static::DEFAULT_RULE] = array_merge($rules[static::DEFAULT_RULE], [
            'is_active' => 'boolean',
            'type' => ['required', 'string', Rule::enum(FieldType::class)],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255', 'unique:fields,name'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255', 'unique:fields,name,' . $this->id],
        ]);
        return $rules;
    }
}
