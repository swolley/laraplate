<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Support\Str;
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
            if (Str::contains($rule, ["sort.", "group_by.", "relations."])) {
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
