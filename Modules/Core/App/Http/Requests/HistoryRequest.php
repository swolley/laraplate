<?php

namespace Modules\Core\App\Http\Requests;

use Modules\Core\App\Casts\HistoryRequestData;

class HistoryRequest extends DetailRequest
{
    #[\Override]
    public function rules()
    {
        return parent::rules() + [
            'limit' => 'integer|min:1|nullable',
        ];
    }

    #[\Override]
    public function parsed(): HistoryRequestData
    {
        return new HistoryRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }
}
