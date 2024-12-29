<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Support\Carbon;
use Modules\Core\Models\DynamicEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Overtrue\LaravelVersionable\VersionStrategy;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Overtrue\LaravelVersionable\Version as OvertrueVersion;

/**
 * @mixin IdeHelperVersion
 */
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
    public static function createForModel(Model $model, $replacements = [], $time = null): Version
    {
        // parent logic because it's not possible to bypass the save action

        /* @var \Overtrue\LaravelVersionable\Versionable|Model $model */
        $versionClass = $model->getVersionModel();
        $versionConnection = $model->getConnectionName();
        $userForeignKeyName = $model->getUserForeignKeyName();

        $version = new $versionClass;
        $version->setConnection($versionConnection);

        $version->versionable_id = $model->getKey();
        $version->versionable_type = $model->getMorphClass();
        $version->{$userForeignKeyName} = $model->getVersionUserId();
        $version->contents = $model->getVersionableAttributes($model->getVersionStrategy(), $replacements);

        if ($time) {
            $version->created_at = Carbon::parse($time);
        }

        // custom additional logic

        // if (is_array($version->versionable_id)) {
        //     $versionable_id = '';
        //     foreach ($version->versionable_id as $key => $value) {
        //         $versionable_id .= $key . ':' . $value . ':';
        //     }
        //     $version->versionable_id = rtrim($versionable_id, ':');
        // }
        if (static::isDynamicEntity($model)) {
            $version->connection_ref = $model->getConnection();
            $version->table_ref = $model->getTable();
        }

        $version->save();

        /** @psalm-suppress LessSpecificReturnStatement */
        return $version;
    }

    private function getCompleteVersionable(): ?Model
    {
        /** @var Model $versionable */
        $versionable = $this->versionable;
        if (!$versionable) return null;

        /** @phpstan-ignore property.notFound */
        if ($this->versionable_type) {
            /** @phpstan-ignore property.notFound */
            if ($this->connection_ref) {
                $versionable->setConnection($this->connection_ref);
            }
            /** @phpstan-ignore property.notFound */
            if ($this->table_ref) {
                $versionable->setTable($this->table_ref);
            }
        }

        return $versionable;
    }

    #[\Override]
    public function revertWithoutSaving(): ?Model
    {
        $versionable = $this->getCompleteVersionable();
        if (!$versionable) return null;

        $original = $versionable->getRawOriginal();
        switch ($versionable->getVersionStrategy()) {
            case VersionStrategy::DIFF:
                // v1 + ... + vN
                $versionsBeforeThis = $this->previousVersions()->orderOldestFirst()->get();
                foreach ($versionsBeforeThis as $version) {
                    if (!empty($version->contents)) {
                        $versionable->setRawAttributes(array_merge($original, $version->contents));
                    }
                }
                break;
            case VersionStrategy::SNAPSHOT:
                // v1 + vN
                /** @var \Overtrue\LaravelVersionable\Version $initVersion */
                $initVersion = $versionable->versions()->first();
                if (!empty($initVersion->contents)) {
                    $versionable->setRawAttributes(array_merge($original, $initVersion->contents));
                }
        }

        if (!empty($this->contents)) {
            $versionable->setRawAttributes(array_merge($original, $this->contents));
        }

        return $versionable;
    }

    #[\Override]
    public function previousVersions(): MorphMany
    {
        $versionable = $this->getCompleteVersionable();

        return $versionable->latestVersions()
            ->where(function (Builder $query) {
                $query->where('created_at', '<', $this->created_at)
                    ->orWhere(function (Builder $q) {
                        $q->where('id', '<', $this->getKey())
                            ->where('created_at', '<=', $this->created_at);
                    });
            });
    }

    #[\Override]
    public function nextVersion(): ?static
    {
        $versionable = $this->getCompleteVersionable();

        return $versionable?->versions()
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

    #[\Override]
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
