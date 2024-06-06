<?php

declare(strict_types=1);

namespace Modules\Core\App\Models;

use Override;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\App\Models\DynamicEntity;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\Version as OvertrueVersion;

class Version extends OvertrueVersion
{
    /**
     * @var string[]
     *
     * @psalm-suppress NonInvariantPropertyType
     */
    protected $hidden = [
        'user_id',
        'connection_ref',
        'table_ref',
        'versionable_type',
        'versionable_id',
    ];

    private static function isDynamicEntity(Model $model): bool
    {
        return class_exists(DynamicEntity::class) && $model instanceof DynamicEntity;
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-suppress MoreSpecificReturnType
     */
    #[\Override]
    public static function createForModel(Model|Versionable $model, $attributes = [], $time = null): static
    {
        $versionClass = $model->getVersionModel();
        $versionConnection = $model->getConnectionName();

        $version = new $versionClass();
        $version->setConnection($versionConnection);

        $version->versionable_id = $model->getKey();
        $version->versionable_type = $model->getMorphClass();
        if (static::isDynamicEntity($model)) {
            $version->connection_ref = $model->getConnection();
            $version->table_ref = $model->getTable();
        }
        $version->{\config('versionable.user_foreign_key')} = $model->getVersionUserId();
        $version->contents = $model->getVersionableAttributes($attributes);
        /** @var \DateTimeInterface|null|string $time */
        if ($time) {
            $version->created_at = Carbon::parse($time);
        }

        $version->save();

        /** @psalm-suppress LessSpecificReturnStatement */
        return $version;
    }

    private function getCompleteVersionable(): Model
    {
        /** @var Model */
        $versionable = $this->versionable;
        if ($this->versionable_type) {
            if ($this->connection_ref) {
                $versionable->setConnection($this->connection_ref);
            }
            if ($this->table_ref) {
                $versionable->setTable($this->table_ref);
            }
        }

        return $versionable;
    }

    public function revertWithoutSaving(): ?Model
    {
        $versionable = $this->getCompleteVersionable();

        return $versionable->forceFill($this->contents ?? []);
    }

    public function previousVersion(): ?static
    {
        $versionable = $this->getCompleteVersionable();

        return $versionable->history()
            ->where(function (Builder $query) {
                $query->where('created_at', '<', $this->created_at)
                    ->orWhere(function (Builder $q) {
                        $q->where('id', '<', $this->getKey())
                            ->where('created_at', '<=', $this->created_at);
                    });
            })
            ->first();
    }

    public function nextVersion(): ?static
    {
        $versionable = $this->getCompleteVersionable();

        return $versionable->versions()
            ->where(function (Builder $query) {
                $query->where('created_at', '>', $this->created_at)
                    ->orWhere(function (Builder $q) {
                        $q->where('id', '>', $this->getKey())
                            ->where('created_at', '>=', $this->created_at);
                    });
            })
            ->orderOldestFirst()
            ->first();
    }

    #[Override]
    public function toArray(): mixed
    {
        $serialized = parent::toArray();

        // mask hashed values from json_encode
        foreach ($serialized['versionable_data'] as &$value) {
            if (gettype($value) === 'string' && mb_strlen($value) === 60 && preg_match('/^\$2y\$/', $value)) {
                $value = '[hidden]';
            }
        }

        return $serialized;
    }
}
