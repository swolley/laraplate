<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\App\Casts\CrudRequestData;
use Modules\Core\App\Casts\IParsableRequest;
use Modules\Core\App\Models\DynamicEntity;
use Illuminate\Foundation\Http\FormRequest;

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
        $this->model = DynamicEntity::resolve($this->route()->entity, $connection);
        $this->primaryKey = $this->model->getKeyName();
    }

    #[\Override]
    public function parsed(): CrudRequestData
    {
        return new CrudRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }
}
