<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Support\Str;
use Modules\Core\App\Helpers\HasValidations;
use Modules\Core\App\Casts\IParsableRequest;
use Modules\Core\App\Casts\ModifyRequestData;
// use Illuminate\Foundation\Http\FormRequest;

class ModifyRequest extends CrudRequest implements IParsableRequest
{
    public function rules()
    {
        return [];
    }

    #[\Override]
    protected function prepareForValidation()
    {
        parent::prepareForValidation();

        $to_merge = [
            'filters' => $this->filters ?? [],
        ];
        $is_insert = Str::contains($this->url(), '/insert/');
        $is_update = Str::contains($this->url(), '/update/');
        $is_autoincrement = $this->model->incrementing;
        // force remove unwanted keys if insert and autoincrement
        if (!$is_autoincrement || !$is_insert) {
            $validation = ['required'];
            if ($this->model->getKeyType() === 'int') $validation[] = 'integer';
        } else {
            $validation = ['forget'];
        }

        foreach (is_array($this->primaryKey) ? $this->primaryKey : [$this->primaryKey] as $key) {
            $to_merge[$key] = $validation;
        }

        // if model has built-in validation rules, merge everything into request rules
        if (class_uses_trait($this->model, HasValidations::class)) {
            $main_entity = $this->route()->entity;
            foreach ($this->model->getOperationRules($is_insert ? 'create' : ($is_update ? 'update' : null)) as $attribute => $rule) {
                $key = $this->$attribute ?? $this->{"$main_entity.$attribute"} ?? null;
                if ($key) {
                    $to_merge[$key] = array_unique([...$to_merge[$key], ...$rule]);
                } else {
                    $to_merge[$key] = $rule;
                }

                $to_merge['filters'][] = ['property' => $key, 'value' => $this->$key];
            }
        }

        $this->merge($to_merge);
    }

    #[\Override]
    public function parsed(): ModifyRequestData
    {
        return new ModifyRequestData($this, $this->route()->entity, $this->validated(), $this->primaryKey);
    }
}
