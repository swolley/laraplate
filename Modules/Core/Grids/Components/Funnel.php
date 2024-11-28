<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Components;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Grids\Definitions\ListEntity;
use Illuminate\Database\Eloquent\Relations\Relation;

class Funnel extends ListEntity
{
    private function prepareFunnelFilterProperties(array $list, array &$grouped_filters): void
    {
        foreach ($list as $field => $filter) {
            $path = explode('.', $field);
            array_shift($path);
            $field = array_pop($path);
            $path = implode('.', $path);
            if (!array_key_exists($path, $grouped_filters)) {
                $grouped_filters[$path] = [];
            }
            $filter['property'] = $field;
            $grouped_filters[$path][] = $filter;
        }
    }

    private function getLastWithCountRelationQuery(Builder|Relation $query, string $relation_name, array $columns_filters, string $current_funnel): void
    {
        $query->withCount($relation_name . ' as total');
        $query->withCount([$relation_name . ' as count' => function ($q) use ($columns_filters, $current_funnel) {
            $grouped_filters = [];
            // columns filters
            $this->prepareFunnelFilterProperties($columns_filters, $grouped_filters);
            // other funnels filters
            $this->prepareFunnelFilterProperties(array_filter($this->requestData->funnelsFilters, fn($f) => $f !== $current_funnel, ARRAY_FILTER_USE_KEY), $grouped_filters);

            foreach ($grouped_filters as $path => $entity_filters) {
                if (count(explode('.', $path)) === 1) {
                    foreach ($entity_filters as $filter) {
                        static::applyCorrectWhereMethod($q, $filter['property'], $filter['operator'], $filter['value']);
                    }
                } else {
                    $q->whereHas($path, function ($q2) use ($entity_filters) {
                        foreach ($entity_filters as $filter) {
                            static::applyCorrectWhereMethod($q2, $filter['property'], $filter['operator'], $filter['value']);
                        }
                    });
                }
            }
        }]);
    }

    // private function setInnerCountRelations(Builder|Relation $query, array $steps)
    // {
    // 	$relation = array_shift($steps);
    // 	$aggregator_name = /*empty($steps) ?*/ 'count' /*: 'sum'*/;
    // 	$method = 'with' . ucfirst($aggregator_name);
    // 	$query->$method($relation->getForeignKey() /*$relation->getName(), $relation->getTable() . '_' . $aggregator_name, function ($q) use ($steps) {
    // 		$this->setInnerCountRelations($q, $steps);
    // 	}*/);
    // }

    /**
     * {@inheritDoc}
     */
    protected function getData(): array
    {
        $name = $this->getValueField()->getFullAlias();
        $funnels_data = $this->requestData->funnelsFilters[$name];
        $columns_filters = $this->requestData->filters ?? [];

        $exploded = explode('.', $name);
        $subfix = array_pop($exploded);
        array_shift($exploded);
        $imploded = implode('.', $exploded);
        $starting_entity = $this->getRelationDeeply($imploded);
        $inversed_relationships = $this->getModel()::getInverseRelationshipDeeply($imploded);
        if (!$inversed_relationships || empty($inversed_relationships)) {
            return [];
        }

        $model = $starting_entity->getModel();
        // TODO: da rivedere
        $this->checkColumnsOrGetDefaults($model, $subfix, $funnels_data['columns'] ?? [$subfix]);
        // $columns[] = $this->getValueField()->getName();
        // if (is_array($this->getLabelField())) array_push($columns, ...array_map(fn ($field) => $field->getName(), $this->getLabelField()));
        // else $columns[] = $this->getLabelField()->getName();
        // $columns = array_unique($columns);
        $columns = $this->getAllFields()->map(fn($field) => $field->getName())->toArray();

        $query = $model::query()->select($columns);
        $this->addSortsIntoQuery($query, $funnels_data['sort'] ?? $this->getDefaultSorts($columns, $model));
        if (!empty($funnels_data['value'])) {
            static::applyCorrectWhereMethod($query, $subfix, $funnels_data['operator'], $funnels_data['value']);
        }

        $last_relation = array_pop($inversed_relationships)->getName();
        if (!empty($inversed_relationships)) {
            // deep relation
            $imploded_inversed_relation = implode('.', array_map(fn($r) => $r->getName(), $inversed_relationships));
            $query->with([$imploded_inversed_relation => function ($q) use ($last_relation, $columns_filters, $name) {
                $this->getLastWithCountRelationQuery($q, $last_relation, $columns_filters, $name);
            }]);
        } else {
            // direct relation
            $this->getLastWithCountRelationQuery($query, $last_relation, $columns_filters, $name);
        }

        $data = $query->get();
        $totals = $query->count();

        // Log::debug(static::dumpQuery($query));
        return [$data, $totals];
    }
}
