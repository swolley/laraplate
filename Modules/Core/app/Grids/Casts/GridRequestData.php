<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Casts;

use Modules\Core\Casts\ListRequestData;
use Modules\Core\Grids\Requests\GridRequest;

class GridRequestData extends ListRequestData
{
    public readonly GridAction $action;
    public readonly ?string $globalSearch;
    public array $funnelsFilters;
    public array $optionsFilters;
    public array $changes;
    public readonly array $layout;

    public function __construct(GridAction $action, GridRequest $request, string $mainEntity, array $validated, string|array $primaryKey)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey);
        $this->action = $action;
        $this->layout = self::extractLayout($validated, $mainEntity);
        $this->fixQueryParamsNames($request, $primaryKey);

        if (GridAction::isReadAction($this->action->value)) {
            $this->globalSearch = self::extractGlobalSearchFilters($validated);
            $this->funnelsFilters = self::extractFunnelsFilters($validated);
            $this->optionsFilters = self::extractOptionsFilters($validated);
            $this->changes = null;
        } else {
            $this->globalSearch = null;
            $this->funnelsFilters = null;
            $this->optionsFilters = null;
            $this->changes = self::extractChanges($validated);
        }
    }

    private function extractLayout(array $filters, string $tableName): array
    {
        return $filters['layout'] ?? ['grid_name' => $tableName];
    }

    /**
     * extract global search filter
     */
    private function extractGlobalSearchFilters(array $filters): ?string
    {
        return $filters['search'] ?? null;
    }

    private function matchCorrectFilterData(array &$list, string $defaultOperator): void
    {
        foreach ($list as $property => &$filter) {
            if (!isset($filter['property'])) {
                $filter['property'] = $property;
            }
            if (!isset($filter['operator'])) {
                $filter['operator'] = $defaultOperator;
            }
        }
    }

    /**
     * extract funnels and relative search filters
     */
    private function extractFunnelsFilters(array $filters): ?array
    {
        $funnels_filters = $filters['funnels'] ?? null;
        if ($funnels_filters) {
            $this->matchCorrectFilterData($funnels_filters, 'in');
            foreach ($funnels_filters as &$funnel) {
                $funnel['value'] = !isset($funnel['value']) || $funnel['value'] === [''] ? [] : (is_string($funnel['value']) ? json_decode($funnel['value'], true) : $funnel['value']);
            }
        }

        return $funnels_filters;
    }

    /**
     * extract options and relative search filters
     */
    private function extractOptionsFilters(array $filters): ?array
    {
        $options_filters = $filters['options'] ?? null;
        if ($options_filters) {
            $this->matchCorrectFilterData($options_filters, 'like');
        }

        return $options_filters;
    }

    /**
     * extract changes
     *
     * @return array|null
     */
    private function extractChanges(array $filters/*, string $entityName*/): ?array
    {
        $changes = $filters['changes'] ?? null;

        return $changes;
    }

    /**
     * replace "." with "_" in primary key name because of PHP automatic replacement in query params
     *
     * @param  string|string[]  $primaryKeyName
     * @return string|string[]
     */
    private static function replacePrimaryKeyUnderscores(string|array $primaryKeyName): array|string
    {
        return is_string($primaryKeyName) ? str_replace('.', '_', $primaryKeyName) : array_map(fn($key) => (string) self::replacePrimaryKeyUnderscores($key), $primaryKeyName);
    }

    /**
     * fixes unwanted underscores in qurey params names
     *
     * @param  string  $modelPrimaryKey
     * @return void
     */
    private function fixQueryParamsNames(GridRequest $request, string|array $modelPrimaryKey)
    {
        // - modifica di 1 record: la pk può essere string o un array<string>
        // - modifica di N record: la pk può essere array<string> o array<string[]>
        if (is_string($modelPrimaryKey)) {
            $modelPrimaryKey = [$modelPrimaryKey];
        }
        /** @var string[] $replaced */
        $replaced = self::replacePrimaryKeyUnderscores($modelPrimaryKey);
        if (($this->action === GridAction::UPDATE || $this->action === GridAction::DELETE || $this->action === GridAction::FORCE_DELETE) && empty($replaced)) {
            throw new \BadMethodCallException('PrimaryKey is mandatory for update and delete actions');
        }

        // TODO: da finire di scrivere
        $count = count($replaced);
        $all = $request->query->all();
        for ($i = 0; $i < $count; $i++) {
            if (!array_key_exists($replaced[$i], $all)) {
                continue;
            }

            /** @psalm-suppress InvalidArrayOffset */
            $key_name = $modelPrimaryKey[$i];
            $primary = $all[$replaced[$i]];
            $request->query->add([$key_name => $primary]);
            $request->query->remove($replaced[$i]);
        }
    }
}
