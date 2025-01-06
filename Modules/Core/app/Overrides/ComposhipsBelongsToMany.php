<?php

namespace Modules\Core\Overrides;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ComposhipsBelongsToMany extends BelongsToMany
{
    #[\Override]
    protected function performJoin($query = null)
    {
        $query = $query ?: $this->query;
        $baseTable = $this->related->getTable();

        $query->join($this->table, function ($join) use ($baseTable) {
            // Gestione chiavi composite per la tabella pivot -> parent
            if (is_array($this->foreignPivotKey)) {
                foreach ($this->foreignPivotKey as $index => $foreignPivotKey) {
                    $parentKey = is_array($this->parentKey) ? $this->parentKey[$index] : $this->parentKey;
                    $join->on(
                        $this->getQualifiedForeignPivotKeyName($foreignPivotKey),
                        '=',
                        $this->parent->qualifyColumn($parentKey)
                    );
                }
            } else {
                $join->on(
                    $this->getQualifiedForeignPivotKeyName(),
                    '=',
                    $this->parent->qualifyColumn($this->parentKey)
                );
            }

            // Gestione chiavi composite per la tabella pivot -> related
            if (is_array($this->relatedPivotKey)) {
                foreach ($this->relatedPivotKey as $index => $relatedPivotKey) {
                    $relatedKey = is_array($this->relatedKey) ? $this->relatedKey[$index] : $this->relatedKey;
                    $join->on(
                        $this->getQualifiedRelatedPivotKeyName($relatedPivotKey),
                        '=',
                        $this->related->qualifyColumn($relatedKey)
                    );
                }
            } else {
                $join->on(
                    $this->getQualifiedRelatedPivotKeyName(),
                    '=',
                    $this->related->qualifyColumn($this->relatedKey)
                );
            }
        });

        return $this;
    }

    #[\Override]
    public function addEagerConstraints(array $models)
    {
        $this->query->whereIn(
            is_array($this->foreignPivotKey) 
                ? array_map(fn($key) => $this->getQualifiedForeignPivotKeyName($key), $this->foreignPivotKey)
                : $this->getQualifiedForeignPivotKeyName(),
            $this->getKeys($models, is_array($this->parentKey) ? $this->parentKey : $this->parentKey)
        );
    }

    #[\Override]
    public function getQualifiedForeignPivotKeyName($key = null): string
    {
        return $this->qualifyPivotColumn($key ?? $this->foreignPivotKey);
    }

    #[\Override]
    public function getQualifiedRelatedPivotKeyName($key = null): string
    {
        return $this->qualifyPivotColumn($key ?? $this->relatedPivotKey);
    }

    #[\Override]
    protected function getKeys(array $models, $key = null): array
    {
        $keys = [];
        foreach ($models as $model) {
            if (is_array($key)) {
                $keys[] = array_map(fn($k) => $model->{$k}, $key);
            } else {
                $keys[] = $model->{$key};
            }
        }
        return array_unique($keys, SORT_REGULAR);
    }

    #[\Override]
    protected function addWhereConstraints()
    {
        if (is_array($this->parentKey)) {
            foreach ($this->parentKey as $index => $parentKey) {
                $this->query->where(
                    $this->getQualifiedForeignPivotKeyName($parentKey), '=', $this->parent->{$parentKey}
                );
            }
        } else {
            $this->query->where(
                $this->getQualifiedForeignPivotKeyName(), '=', $this->parent->{$this->parentKey}
            );
        }

        return $this;
    }

    #[\Override]
    public function getResults()
    {
        $is_null = false;
        if (is_array($this->parentKey)) {
            foreach ($this->parentKey as $index => $parentKey) {
                if (is_null($this->parent->{$parentKey})) {
                    $is_null = true;
                    break;
                }
            }
        } else {
            $is_null = is_null($this->parent->{$this->parentKey});
        }
        return ! $is_null
                ? $this->get()
                : $this->related->newCollection();
    }
}
