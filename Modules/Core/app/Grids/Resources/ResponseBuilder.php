<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Resources;

use Illuminate\Support\Collection;
use Modules\Core\Helpers\ResponseBuilder as BaseResponseBuilder;

class ResponseBuilder extends BaseResponseBuilder
{
    private ?string $primaryKey = null;

    private ?string $created = null;

    private ?string $updated = null;

    private ?string $deleted = null;

    private ?string $locked = null;

    private ?string $action = null;

    private array|Collection|null $options = null;

    private array|Collection|null $funnels = null;

    private array|Collection|null $layouts = null;

    /**
     * @param  array<string, string|array>  $rules
     */
    private array $rules = [];

    /**
     * @psalm-suppress LessSpecificImplementedReturnType
     */
    public function toArray(): array
    {
        $payload = [];
        $meta = [];
        // unset($payload['meta']);

        if (!empty($this->rules)) {
            $meta['validations'] = $this->rules;
        }
        if ($this->options) {
            $remapped = [];
            foreach ($this->options as $option) {
                $remapped[] = $option->getPayload();
            }
            $payload['options'] = $remapped;
        }
        if ($this->funnels) {
            $remapped = [];
            foreach ($this->funnels as $funnel) {
                $remapped[] = $funnel->getPayload();
            }
            $payload['funnels'] = $remapped;
        }
        if ($this->layouts) {
            $payload['layouts'] = $this->layouts;
        }
        $meta['primaryKey'] = $this->primaryKey;
        $meta['created'] = $this->created;
        $meta['updated'] = $this->updated;
        $meta['deleted'] = $this->deleted;
        $meta['locked'] = $this->locked;
        $meta['action'] = $this->action;

        $payload['meta'] = $meta;

        return $payload;
    }

    /**
     * Set the value of primaryKey
     */
    public function setPrimaryKey(?string $primaryKey): static
    {
        $this->primaryKey = $primaryKey;

        return $this;
    }

    /**
     * Set the value of created
     */
    public function setCreated(?string $created): static
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Set the value of updated
     */
    public function setUpdated(?string $updated): static
    {
        $this->updated = $updated;

        return $this;
    }

    /**
     * Set the value of deleted
     */
    public function setDeleted(?string $deleted): static
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * Set the value of locked
     */
    public function setLocked(?string $locked): static
    {
        $this->locked = $locked;

        return $this;
    }

    /**
     * Set the value of rules
     *
     * @param  array<string, string|array>  $rules
     */
    public function setRules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Set the value of options
     *
     * @psalm-param Collection<string, \Modules\Core\Helpers\ResponseBuilder>|null $options
     */
    public function setOptions(Collection|array|null $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Set the value of funnels
     *
     * @psalm-param Collection<string, \Modules\Core\Helpers\ResponseBuilder>|null $funnels
     */
    public function setFunnels(Collection|array|null $funnels): static
    {
        $this->funnels = $funnels;

        return $this;
    }

    /**
     * Set the value of layouts
     */
    public function setLayouts(array|Collection|null $layouts): static
    {
        $this->layouts = $layouts;

        return $this;
    }

    /**
     * Set the value of action
     */
    public function setAction(?string $action): static
    {
        $this->action = $action;

        return $this;
    }
}
