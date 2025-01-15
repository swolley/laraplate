<?php

namespace Modules\Cms\Models;

use Spatie\Image\Enums\Fit;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Helpers\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Modules\Cms\Models\Pivot\Authorable;
use Modules\Core\Helpers\HasValidations;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Modules\Cms\Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperAuthor
 */
class Author extends Model
{
    use HasFactory, SoftDeletes, HasVersions, HasValidations, InteractsWithMedia {
        getRules as protected getRulesTrait;
    }

    protected $fillable = [
        'name',
        'public_email',
    ];

    protected $hidden = [
        'user_id',
        'user',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $tempUser;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): AuthorFactory
    {
        return AuthorFactory::new();
    }

    protected function getCanLoginAttribute(): bool
    {
        return $this->user !== null || $this->tempUser !== null;
    }

    protected function getIsSignatureAttribute(): bool
    {
        return !$this->getCanLoginAttribute();
    }

    /**
     * @return BelongsTo<User>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(user_class());
    }

    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'authorables')->using(Authorable::class)->withTimestamps();
    }

    // Magic getter for user attributes
    #[\Override]
    public function __get($key)
    {
        $entity = new User();
        if (in_array($key, $entity->getFillable())) {
            return $this->user ? $this->user->{$key} : null;
        }
        return parent::__get($key);
    }

    // Magic setter for user attributes
    #[\Override]
    public function __set($key, $value)
    {
        $session_user = Auth::user();
        $entity = new User();
        $table = $entity->getTable();
        $user_can_insert = $session_user && $session_user->can("$table.create");
        $user_can_update = $session_user && $session_user->can("$table.update");
        if (in_array($key, $entity->getFillable()) && ($user_can_insert || $user_can_update)) {
            if (!$this->user && !$user_can_insert) {
                throw new UnauthorizedException("User cannot insert $entity");
            }

            if (!$this->user && !isset($this->tempUser) && $user_can_insert) {
                $this->tempUser = new User();
                $this->tempUser->{$key} = $value;
            } else if ($user_can_update) {
                $this->user->{$key} = $value;
            }
            return;
        }
        parent::__set($key, $value);
    }

    // Save method to handle user creation/updating
    #[\Override]
    public function save(array $options = [])
    {
        if (isset($this->tempUser) && $this->tempUser->isDirty()) {
            $this->tempUser->save();
            $this->user_id = $this->tempUser->id;
            unset($this->tempUser);
            $this->load('user');
        } else if ($this->user && $this->user->isDirty()) {
            $this->user->save();
        }
        parent::save($options);
    }

    #[\Override]
    public function toArray(): array
    {
        $author = parent::toArray();
        $user = $this->user ? $this->user->toArray() : null;
        return $user ? array_merge($author, $user) : $author;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->singleFile();
        // $this->addMediaCollection('videos')
        // 	->extractVideoFrameAtSecond(2);
        // $this->addMediaCollection('audios');
        // $this->addMediaCollection('files');
    }


    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('images'/*, 'videos'*/)
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->fit(Fit::Fill, 300, 300);
    }

    protected function picture(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->getFirstMediaUrl('images'),
            set: fn($value) => $this->addMedia($value)->toMediaCollection('images'),
        );
    }

    // public function getPictureAttribute(): string
    // {
    //     return $this->getFirstMediaUrl('images', 'thumb');
    // }

    public function getRules(): array
    {
        $rules = $this->getRulesTrait();
        $rules[static::DEFAULT_RULE] = array_merge($rules[static::DEFAULT_RULE], [
            'name' => ['required', 'string', 'max:255', 'unique:authors,name'],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255', 'unique:authors,name'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255', 'unique:authors,name,' . $this->id],
        ]);
        return $rules;
    }
}
