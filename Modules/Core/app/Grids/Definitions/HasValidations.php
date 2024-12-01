<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

// use Illuminate\Http\Request;

trait HasValidations
{
    private array $validation;

    /**
     * get validation
     */
    public function getValidation(): ?array
    {
        return isset($this->validation) ? $this->validation : null;
    }

    /**
     * Undocumented function

     *
     * @return void
     */
    private function setValidation(array|string|null $rule)
    {
        $this->validation = is_string($rule) ? explode('|', $rule) : $rule;
    }

    public function validation($rule = null): static
    {
        $this->setValidation($rule);

        return $this;
    }
}
