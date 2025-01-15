<?php

namespace Modules\Cms\Models;

use ArrayAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Cms\Helpers\HasPath;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidations;
use Modules\Cms\Database\Factories\TagFactory;
use Modules\Cms\Helpers\HasSlug;
use Illuminate\Database\Eloquent\Collection as DbCollection;
use Illuminate\Support\Collection;

/**
 * @mixin IdeHelperTag
 */
class Tag extends Model implements Sortable
{
    use HasValidations, HasPath, SortableTrait, HasSlug, HasFactory {
        getRules as protected getRulesTrait;
    }

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

    public function scopeWithType(Builder $query, ?string $type = null): Builder
    {
        if (is_null($type)) {
            return $query;
        }

        return $query->where('type', $type)->ordered();
    }

    public function scopeContaining(Builder $query, string $name, $locale = null): Builder
    {
        $locale = $locale ?? static::getLocale();

        return $query->whereRaw('lower(' . $this->getQuery()->getGrammar()->wrap('name') . ') like ?', ['%' . mb_strtolower($name) . '%']);
    }

    public static function findOrCreate(
        string | array | ArrayAccess $values,
        string | null $type = null,
        string | null $locale = null,
    ): Collection | Tag | static {
        $tags = collect($values)->map(function ($value) use ($type, $locale) {
            if ($value instanceof self) {
                return $value;
            }

            return static::findOrCreateFromString($value, $type, $locale);
        });

        return is_string($values) ? $tags->first() : $tags;
    }

    public static function getWithType(string $type): DbCollection
    {
        return static::withType($type)->get();
    }

    public static function findFromString(string $name, ?string $type = null)
    {
        $locale = $locale ?? static::getLocale();

        return static::query()
            ->where('type', $type)
            ->where(function ($query) use ($name) {
                $query->where("name", $name)
                    ->orWhere("slug", $name);
            })
            ->first();
    }

    public static function findFromStringOfAnyType(string $name)
    {
        return static::query()
            ->where("name", $name)
            ->orWhere("slug", $name)
            ->get();
    }

    public static function findOrCreateFromString(string $name, ?string $type = null)
    {
        $tag = static::findFromString($name, $type);

        if (! $tag) {
            $tag = static::create([
                'name' => $name,
                'type' => $type,
            ]);
        }

        return $tag;
    }

    public static function getTypes(): Collection
    {
        return static::groupBy('type')->pluck('type');
    }

    public function setAttribute($key, $value)
    {
        return parent::setAttribute($key, $value);
    }
}
