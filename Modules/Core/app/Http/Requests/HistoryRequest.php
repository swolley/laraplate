<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Casts\HistoryRequestData;

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
        /** @phpstan-ignore method.notFound */
        return new HistoryRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }
}
