<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Casts\TreeRequestData;

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
        /** @phpstan-ignore method.notFound */
        return new TreeRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }
}
