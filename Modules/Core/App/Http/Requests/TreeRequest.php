<?php

namespace Modules\Core\App\Http\Requests;

use Modules\Core\App\Casts\TreeRequestData;

class TreeRequest extends DetailRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @psalm-suppress LessSpecificImplementedReturnType
     *
     * @return array
     */
    #[\Override]
    public function rules()
    {
        return parent::rules() + [
            'parents' => 'boolean',
            'children' => 'boolean',
        ];
    }

    #[\Override]
    public function parsed(): TreeRequestData
    {
        return new TreeRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }
}
