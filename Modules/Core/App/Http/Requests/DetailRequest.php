<?php

namespace Modules\Core\App\Http\Requests;

use Modules\Core\App\Casts\DetailRequestData;

class DetailRequest extends SelectRequest
{
    #[\Override]
    protected function prepareForValidation()
    {
        parent::prepareForValidation();

        $to_merge = [
            'filters' => []
        ];

        foreach (is_array($this->primaryKey) ? $this->primaryKey : [$this->primaryKey] as $key) {
            $to_merge[$key] = ['required'];
            $to_merge['filters'][] = ['property' => $key, 'value' => $this->$key];
        }

        $this->merge($to_merge);
    }

    #[\Override]
    public function parsed(): DetailRequestData
    {
        return new DetailRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }
}
