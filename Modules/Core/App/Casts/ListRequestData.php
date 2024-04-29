<?php

declare(strict_types=1);

namespace Modules\Core\App\Casts;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Core\Models\Setting;
use Modules\Core\App\Http\Requests\ListRequest;

class ListRequestData extends SelectRequestData
{
    public readonly int $pagination;

    public readonly ?int $page;

    public readonly ?int $skip;

    public readonly ?int $take;

    public readonly ?int $from;

    public readonly ?int $to;

    public readonly ?int $limit;

    public readonly bool $count;

    public readonly array $sort;

    public readonly ?FiltersGroup $filters;

    public array $group_by;

    /**
     * @var string|string[] $primaryKey
     */
    public function __construct(ListRequest $request, string $mainEntity, array $validated, string|array $primaryKey)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey);
        $this->conformAndSetPagination($validated);
        $this->limit = $validated['limit'] ?? $this->pagination;
        $this->count = $validated['count'] ?? false;
        $this->sort = $this->conformSorts($validated['sorts'] ?? []);

        if (isset($validated['filters'])) {
            $this->conformFiltersToQueryBuilderFormat($validated['filters']);
            $this->filters = $validated['filters'];
        }

        if (isset($validated['group_by'])) {
            $this->group_by = $validated['group_by'];
            $this->addGroupsToColumns($validated['group_by']);
        }
    }

    private static function getDefaultPagination(): ?int
    {
        return Setting::whereName('pagination')->first('value')?->value ?? 25;
    }

    public function calculateTotalPages(int $totalRecords): int
    {
        return (int) ceil($totalRecords / $this->pagination);
    }

    protected function conformFilterOperators(array &$filter): void
    {
        if (array_key_exists('operator', $filter)) {
            $filter['operator'] = FilterOperator::tryFromRequestOperator($filter['operator']);
        } elseif (array_key_exists('value', $filter)) {
            $filter['operator'] = FilterOperator::EQUALS;
        }
    }

    /**
     * @param array{property: string; value: mixed; operator: FilterOperator} $filter
     */
    protected function conformFilterValue(array &$filter): void
    {
        if ($filter['value'] == 'null') {
            $filter['value'] = null;
        } elseif (in_array($filter['operator'], [FilterOperator::LIKE, FilterOperator::NOT_LIKE], true)) {
            $filter['value'] = !Str::startsWith($filter['value'], '%') && !Str::endsWith($filter['value'], '%') ? '%' . $filter['value'] . '%' : $filter['value'];
        } elseif ($filter['operator'] === FilterOperator::IN && is_string($filter['value'])) {
            $filter['value'] = is_json($filter['value']) ? json_decode($filter['value'], true) : explode(',', $filter['value']);
        }
    }

    protected function conformFiltersToQueryBuilderFormat(array &$filters, int $level = 0): void
    {
        if (Arr::isList($filters)) {
            foreach ($filters as &$filter) {
                $this->conformFiltersToQueryBuilderFormat($filter, $level + 1);
            }
        } elseif (Arr::has($filters, 'filters')) {
            $filters['operator'] = isset($filters['operator']) ? WhereClause::tryFrom(mb_strtolower($filters['operator'])) : WhereClause::AND;
            $this->conformFiltersToQueryBuilderFormat($filters['filters'], $level + 1);
            $filters = new FiltersGroup($filters['filters'], $filters['operator']);
        } else {
            $this->conformFilterOperators($filters);
            $this->conformFilterValue($filters);
            $filter = new Filter($filters['property'], $filters['value'], $filters['operator']);
        }

        if ($level === 0 && Arr::isList($filters)) {
            $filters = new FiltersGroup($filters);
        }
    }

    private function conformAndSetPagination(array $validated): void
    {
        if (isset($validated['pagination']) || isset($validated['page'])) {
            $this->take = $this->pagination = $validated['pagination'] ?? static::getDefaultPagination();
            $this->page = $validated['page'] ?? 1;
            $this->skip = ($this->page - 1) * $this->pagination;
            $this->from = $this->skip + 1;
            $this->to = $this->from + $this->pagination;
        } elseif (isset($validated['from']) || isset($validated['to'])) {
            $this->from = $validated['from'] ?? 1;
            $this->skip = $this->from - 1;
            $this->to = $validated['to'] ?? null;

            if ($this->to) {
                $this->take = $this->pagination = $this->to - $this->from;
            }
        } elseif (isset($validated['limit'])) {
            $this->take = $this->limit = $validated['limit'];
            $this->page = 0;
            $this->skip = 0;
            $this->pagination = $validated['limit'];
        } else {
            $this->page = 0;
            $this->skip = 0;
            $this->take = $this->pagination = static::getDefaultPagination();
        }
    }

    /**
     *
     * @return Sort[]
     */
    private function conformSorts(array $sorts): array
    {
        foreach ($sorts as &$value) {
            if (is_string($value)) {
                $value = new Sort($value);
            } else {
                $value = new Sort($value['property'], $value['direction'] ?? SortDirection::ASC);
            }
        }

        return $sorts;
    }

    /**
     * @param  string[]  $groups
     * @return string[]
     */
    private function addGroupsToColumns(array $groups): void
    {
        if (!empty($this->columns)) {
            $all_columns_name = array_map(fn ($column) => is_string($column) ? $column : $column['name'], $this->columns);

            foreach ($groups as $group) {
                if (!in_array($group, $all_columns_name, true)) {
                    $this->columns[] = new Column($group);
                }
            }
        }
    }
}
