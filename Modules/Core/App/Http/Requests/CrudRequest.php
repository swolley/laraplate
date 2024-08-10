<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\App\Models\DynamicEntity;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\App\Casts\CrudRequestData;
use Modules\Core\App\Casts\IParsableRequest;

abstract class CrudRequest extends FormRequest implements IParsableRequest
{
    /** @var string|string[] */
    protected string|array $primaryKey;

    protected Model $model;

    public function rules()
    {
        return [
            'connection' => ['string'],
        ];
    }

    protected function prepareForValidation()
    {
        $connection = $this->connection ?? null;
        /** @phpstan-ignore method.notFound */
        $this->model = DynamicEntity::resolve($this->route()->entity, $connection);
        $this->primaryKey = $this->model->getKeyName();
    }

    #[\Override]
    public function parsed(): CrudRequestData
    {
        /** @phpstan-ignore method.notFound */
        return new CrudRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }
}
