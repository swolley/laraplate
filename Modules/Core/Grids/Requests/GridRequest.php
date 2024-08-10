<?php

namespace Modules\Core\Grids\Requests;

use Modules\Core\App\Casts\GridAction;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\App\Casts\CrudRequestData;
use Modules\Core\App\Casts\IParsableRequest;
use Symfony\Component\HttpFoundation\InputBag;
use Modules\Core\App\Http\Requests\CrudRequest;
use Modules\Core\App\Http\Requests\ListRequest;
use Modules\Core\App\Http\Requests\ModifyRequest;

class GridRequest extends FormRequest implements IParsableRequest
{
    private CrudRequest $realRequest;

    public function rules()
    {
        $actions = implode(',', GridAction::values());

        $grid_rules = [
            'action' => ["in:$actions"],
        ];

        if ($this->action === GridAction::FUNNELS) {
            $grid_rules = array_merge($grid_rules, [
                'funnels.*' => 'sometimes',
                'options.*' => 'sometimes',
            ]);
        } else if ($this->action === GridAction::OPTIONS) {
        }

        if (isset($this->realRequest)) {
            $grid_rules = array_merge($this->realRequest->rules(), $grid_rules);
        }

        return $grid_rules;
    }

    #[\Override]
    protected function prepareForValidation()
    {
        /** @var GridAction $action */
        $action = $this->action;
        switch ($action) {
            case GridAction::DATA:
            case GridAction::SELECT:
            case GridAction::EXPORT:
            case GridAction::GET_ALL:
            case GridAction::FUNNELS:
            case GridAction::OPTIONS:
                /** @phpstan-ignore staticMethod.notFound */
                $this->realRequest = ListRequest::createFrom($this);
                break;
                // case GridAction::LAYOUT:
                // case GridAction::COUNT:
            case GridAction::INSERT:
            case GridAction::UPDATE:
                /** @phpstan-ignore staticMethod.notFound */
                $this->realRequest = ModifyRequest::createFrom($this);
                break;
            case GridAction::CHECK:
            case GridAction::FORCE_DELETE:
            case GridAction::DELETE:
                // case GridAction::RESTORE:
                break;
        }
    }

    #[\Override]
    public function validateResolved()
    {
        parent::validateResolved();

        if (isset($this->realRequest)) {
            $this->realRequest->validateResolved();
        }
    }

    protected function getInputSource()
    {
        /** @var InputBag $input_source */
        /** @phpstan-ignore staticMethod.notFound */
        $input_source = parent::getInputSource();

        if (isset($this->realRequest)) {
            if ($this->realRequest->isJson()) {
                $real_input_source = $this->realRequest->json();
            } else {
                $real_input_source = in_array($this->realRequest->getRealMethod(), ['GET', 'HEAD'])
                    ? $this->realRequest->query
                    : $this->realRequest->request;
            }

            $input_source->add($real_input_source->all());
        }

        return $input_source;
    }

    #[\Override]
    public function parsed(): CrudRequestData
    {
        /** @var string $main_entity */
        /** @phpstan-ignore method.notFound */
        $main_entity = $this->route()->entity;
        return new CrudRequestData($this, $main_entity, $this->validated(), $this->primaryKey);
    }
}
