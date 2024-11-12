<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Requests;

use Illuminate\Support\Arr;
use Modules\Core\Grids\Casts\GridAction;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\App\Casts\IParsableRequest;
use Modules\Core\Grids\Casts\GridRequestData;
use Modules\Core\App\Http\Requests\ListRequest;
use Modules\Core\App\Http\Requests\ModifyRequest;

class GridRequest extends FormRequest implements IParsableRequest
{
    private GridAction $action;

    private ListRequest|ModifyRequest $realMainRequest;
    /**
     * @var ListRequest[]
     */
    private array $realOptionRequests = [];
    /**
     * @var ListRequest[]
     */
    private array $realFunnelRequests = [];

    public function rules()
    {
        if ($this->action === GridAction::FUNNELS) {
            $grid_rules = $this->remapListRules('funnels.*');
        } else if ($this->action === GridAction::OPTIONS) {
            $grid_rules = $this->remapListRules('options.*');
        } else if ($this->action === GridAction::SELECT) {
            $grid_rules = [
                'options' => ['sometimes'],
                'funnels' => ['sometimes'],
                ...$this->remapListRules('funnels.*'),
                ...$this->remapListRules('options.*'),
            ];
            // TODO: serve anche l'entità o parto da quella della griglia e poi guardo che colonne vengono chieste?
        } else {
            $common_rules = [
                'exclude_if:action,' . GridAction::INSERT->value,
                'exclude_if:action,' . GridAction::UPDATE->value,
                'exclude_if:action,' . GridAction::DELETE->value,
                'exclude_if:action,' . GridAction::FORCE_DELETE->value,
                'exclude_if:action,' . GridAction::APPROVE->value,
                'exclude_if:action,' . GridAction::LOCK->value,
                'exclude_if:action,' . GridAction::CHECK->value,
            ];
            $grid_rules['options'] = $common_rules;
            $grid_rules['funnels'] = $common_rules;
        }

        return $grid_rules;
    }

    private function remapListRules(string $prefix): array
    {
        $list_rules = Arr::except((new ListRequest)->rules(), ['count', 'group_by.*']);
        return $this->remapRules($list_rules, $prefix);
    }

    private function remapRules(array $rules, string $prefix): array
    {
        $remapped = [];
        foreach ($rules as $name => $validations) {
            $remapped["$prefix.$name"] = $validations;
        }

        return $remapped;
    }

    #[\Override]
    protected function prepareForValidation()
    {
        parent::prepareForValidation();

        $exploded_url = explode('/', $this->url());
        $this->action = GridAction::from($exploded_url[count($exploded_url) - 2]);

        switch ($this->action) {
            case GridAction::DATA:
            case GridAction::SELECT:
            case GridAction::EXPORT:
            case GridAction::FUNNELS:
            case GridAction::OPTIONS:
                /** @phpstan-ignore staticMethod.notFound */
                $this->realMainRequest = ListRequest::createFrom($this);
                $this->realMainRequest->setContainer($this->container);
                if (isset($this->funnels)) {
                    foreach ($this->funnels as $funnel) {
                        $sub_request = ListRequest::createFrom($this);
                        $sub_request->setContainer($this->container);
                        $sub_request->replace($funnel);
                        $this->realFunnelRequests[] = $sub_request;
                    }
                }
                if (isset($this->options)) {
                    foreach ($this->options as $option) {
                        $sub_request = ListRequest::createFrom($this);
                        $sub_request->setContainer($this->container);
                        $sub_request->replace($funnel);
                        $this->realOptionRequests[] = $sub_request;
                    }
                }
                break;
                // case GridAction::LAYOUT:
                // case GridAction::COUNT:
            case GridAction::INSERT:
            case GridAction::UPDATE:
                /** @phpstan-ignore staticMethod.notFound */
                // $this->realMainRequest = ModifyRequest::createFrom($this);
                // break;
            case GridAction::CHECK:
            case GridAction::FORCE_DELETE:
            case GridAction::DELETE:
                // case GridAction::RESTORE:
                $this->realMainRequest = ModifyRequest::createFrom($this);
                $this->realMainRequest->setContainer($this->container);
                break;
        }
    }

    #[\Override]
    public function validateResolved()
    {
        parent::validateResolved();

        if (isset($this->realMainRequest)) {
            $this->realMainRequest->validateResolved();
        }
        if (!empty($this->realOptionRequests)) {
            foreach ($this->realOptionRequests as $request) {
                $request->validateResolved();
            }
        }
        if (!empty($this->realFunnelRequests)) {
            foreach ($this->realFunnelRequests as $request) {
                $request->validateResolved();
            }
        }
    }

    public function validated($key = null, $default = null)
    {
        $validated = $this->realMainRequest->validated($key, $default);
        if ($this->funnels) {
            for ($i = 0; count($this->funnels); $i++) {
                $validated['funnels'][$i] = $this->realFunnelRequests[$i]->validated();
            }
        }
        if ($this->options) {
            for ($i = 0; count($this->options); $i++) {
                $validated['options'][$i] = $this->realOptionRequests[$i]->validated();
            }
        }

        return $validated;
    }

    #[\Override]
    public function parsed(): GridRequestData
    {
        /** @var string $main_entity */
        /** @phpstan-ignore method.notFound */
        $main_entity = $this->route()->entity;
        $remapped = $this->validated();

        return new GridRequestData($this->action, $this, $main_entity, $remapped, $this->realMainRequest->getPrimaryKey());
    }
}
