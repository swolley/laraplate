<?php

namespace Modules\Core\App\Http\Requests;

use Modules\Core\App\Rules\QueryBuilder;
use Modules\Core\App\Casts\ListRequestData;

class ListRequest extends SelectRequest
{
    #[\Override]
    public function rules()
    {
        $rules = parent::rules() + [
            'pagination' => ['integer', 'min:1', 'exclude_if:count,true'],
            'page' => ['integer', 'min:1', 'exclude_if:count,true'],
            'from' => ['integer', 'min:1', 'exclude_if:count,true'],
            'to' => ['integer', 'min:1', 'exclude_if:count,true'],
            'limit' => ['integer', 'min:1', 'exclude_if:count,true'],
            'count' => ['boolean'],
            'sort.*.property' => ['string'],
            'sort.*.direction' => ['in:asc,desc,ASC,DESC'],
            'filters' => [new QueryBuilder],
            'group_by.*' => ['string'],
        ];
        $rules['relations.*'][] = 'exclude_if:count,true';

        return $rules;
    }



    #[\Override]
    protected function prepareForValidation()
    {
        parent::prepareForValidation();

        $to_merge = [];
        if (isset($this->sort)) {
            $to_merge['sort'] = is_string($this->sort) && is_json($this->sort) ? json_decode($this->sort, true) : (is_string($this->sort) ? preg_split("/,\s?/", $this->sort) : $this->sort);
        }
        if (isset($this->filters)) {
            $to_merge['filters'] = (is_string($this->filters) && is_json($this->filters)) ? json_decode($this->filters, true) : $this->filters;
        }
        if (isset($this->filters)) {
            $to_merge['group_by'] = (is_string($this->group_by) && is_json($this->group_by)) ? json_decode($this->group_by, true) : $this->group_by;
        }

        $this->merge($to_merge);
    }

    #[\Override]
    public function parsed(): ListRequestData
    {
        return new ListRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }
}
