<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Casts\SearchRequestData;

class SearchRequest extends ListRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules["count"]);
        foreach (array_keys($rules) as $rule) {
            if (strpos($rule, "sort.") !== false || strpos($rule, "group_by.") !== false || strpos($rule, "relations.") !== false) {
                unset($rules[$rule]);
            }
        }
        return $rules + [
            'qs' => ['string', 'required'],
        ];
    }

    #[\Override]
    public function parsed(): SearchRequestData
    {
        /** @phpstan-ignore method.notFound */
        return new SearchRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }
}
