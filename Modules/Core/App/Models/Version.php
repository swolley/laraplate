<?php

declare(strict_types=1);

namespace Modules\Core\App\Models;

use Override;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\App\Models\DynamicEntity;

class Version extends \Mpociot\Versionable\Version
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

    // /**
    //  * {@inheritDoc}
    //  */
    // #[\Override]
    // public static function createForModel(Model|VersionableTrait $model, $attributes = [], $time = null): static
    // {
    //     $versionClass = $model->getVersionClass();
    //     $versionConnection = $model->getConnectionName();

    //     $version = new $versionClass();
    //     $version->setConnection($versionConnection);

    //     $version->versionable_id = $model->getKey();
    //     $version->versionable_type = $model->getMorphClass();
    //     if (static::isDynamicEntity($model)) {
    //         $version->connection_ref = $model->getConnection();
    //         $version->table_ref = $model->getTable();
    //     }
    //     $version->user_id = $model->getVersionUserId();
    //     $version->content_data = $model->getVersionableAttributes($attributes);
    //     /** @var \DateTimeInterface|null|string $time */
    //     if ($time) {
    //         $version->created_at = Carbon::parse($time);
    //     }

    //     $version->save();

    //     /** @psalm-suppress LessSpecificReturnStatement */
    //     return $version;
    // }

    #[Override]
    public function getModel()
    {
        $model = parent::getModel();

        if (static::isDynamicEntity($model)) {
            if ($this->connection_ref) {
                $model->setConnection($this->connection_ref);
            }

            if ($this->table_ref) {
                $model->setTable($this->table_ref);
            }
        }

        return $model;
    }

    #[Override]
    public function toArray(): mixed
    {
        $serialized = parent::toArray();

        // mask hashed values from json_encode
        foreach ($serialized['model_data'] as &$value) {
            if (gettype($value) === 'string' && mb_strlen($value) === 60 && preg_match('/^\$2y\$/', $value)) {
                $value = '[hidden]';
            }
        }

        return $serialized;
    }
}
