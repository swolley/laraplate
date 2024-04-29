<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Casts;

use Illuminate\Support\Str;
use Modules\Core\Grids\Requests\GridRequest;

readonly class GridRequestData implements \JsonSerializable
{
    private GridRequest $request;

    private string $action;

    private ?array $pagination;

    private ?string $globalSearch;

    private ?array $columns;

    private ?array $columnsFilters;

    private ?array $orders;

    private ?array $funnelsFilters;

    private ?array $optionsFilters;

    private ?array $relations;

    private ?array $changes;

    private string|array|null $primaryKey;

    private ?array $layout;

    public function __construct(GridRequest $request, string $entityName, string $modelPrimaryKey)
    {
        $this->request = $request;
        $filters = $request->validated();
        $this->action = static::extractAction($filters);
        $this->layout = static::extractLayout($filters, $entityName);
        $this->fixQueryParamsNames($request, $modelPrimaryKey);

        if (GridAction::isReadAction($this->action, $request->getMethod())) {
            $this->pagination = static::extractPagination($filters);
            $this->globalSearch = static::extractGlobalSearchFilters($filters);
            $this->columns = static::extractColumns($filters, $entityName);
            $this->columnsFilters = static::extractColumnsFilters($filters);
            $this->funnelsFilters = static::extractFunnelsFilters($filters);
            $this->optionsFilters = static::extractOptionsFilters($filters);
            $this->orders = static::extractOrdersFilters($filters);
            $this->relations = static::extractRelations($filters, $entityName);
            $this->changes = static::extractChanges($filters);
        } else {
            $this->primaryKey = static::extractPrimaryKey($filters);
        }
    }

    /**
     * Get the value of request
     */
    public function getRequest(): GridRequest
    {
        return $this->request;
    }

    /**
     * Get the value of action
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Get the value of pagination
     */
    public function getPagination(): ?array
    {
        return $this->pagination ?? null;
    }

    /**
     * Get the value of globalSearch
     */
    public function getGlobalSearch(): ?string
    {
        return $this->globalSearch ?? null;
    }

    /**
     * Get the value of columnsFilters
     */
    public function getColumnsFilters(): ?array
    {
        return $this->columnsFilters ?? null;
    }

    /**
     * Get the value of columns
     */
    public function getColumns(): ?array
    {
        return $this->columns ?? null;
    }

    /**
     * Get the value of orders
     */
    public function getOrders(): ?array
    {
        return $this->orders ?? null;
    }

    /**
     * Get the value of primaryKey
     */
    public function getPrimaryKey(): string|array|null
    {
        return $this->primaryKey ?? null;
    }

    /**
     * Get the value of layout
     */
    public function getLayout(): ?array
    {
        return $this->layout;
    }

    /**
     * Get the value of relations
     */
    public function getRelations(): ?array
    {
        return $this->relations ?? null;
    }

    /**
     * Get the value of optionsFilters
     */
    public function getOptionsFilters(): ?array
    {
        return $this->optionsFilters ?? null;
    }

    /**
     * Get the value of funnelsFilters
     */
    public function getFunnelsFilters(): ?array
    {
        return $this->funnelsFilters;
    }

    /**
     * Get the value of changes
     */
    public function getChanges(): ?array
    {
        return $this->changes;
    }

    private function extractLayout(array $filters, string $tableName): array
    {
        return $filters['layout'] ?? ['grid_name' => $tableName];
    }

    /**
     * extract pagination data from request
     * PAGINATION examples:
     * - ?page=<int>      -> da pagina + default pagination
     * - ?page=<int>&pagination=<int> -> da pagina + pagination
     * -  ?from=<int>      -> da record + default pagination
     * - ?from=<int>&pagination=<int> -> da record + pagination
     * - ?from=<int>&to=<int>   -> da recod a record
     *
     *
     * @return (int|mixed)[]
     *
     * @psalm-return array{from: int|mixed, to: int|mixed}
     */
    private function extractPagination(array $filters): array
    {
        $page = $filters['page'] ?? null;
        $pagination = $filters['pagination'] ?? 25;
        $from = $filters['from'] ?? 1;
        $to = $filters['to'] ?? null;

        if ($page !== null) {
            return ['from' => (($page ?? 1) - 1) * $pagination, 'to' => ($page * $pagination) - 1];
        }
        if ($to !== null) {
            return ['from' => (int) $from, 'to' => (int) $to];
        }

        return ['from' => (int) $from, 'to' => (int) $from + (int) $pagination];
    }

    /**
     * extract action
     * ACTION examples:
     * - ?action=get   -> completa
     * - ?action=funnels  -> elaboro solo i funnels, filtri e/o paginazione
     * - ?action=options  -> elaboro solo le options, filtri e/o paginazione
     * - ?action=data   -> elaboro solo i records, filtri e/o paginazione
     * - ?action=export  -> esporta nei formati supportati
     * - ?action=check   -> verifica concorrenza con modifica effettuate da altri utenti
     * - ?action=create  -> inserisci record
     * - ?action=save   -> aggiorna uno o più records
     * - ?action=delete  -> cancella uno o più records
     * - ?action=soft_delete -> cancella logicamente uno o più records
     * - ?action=userconfig -> crud su layouts e impostazioni utente
     *
     *
     *
     * @psalm-return 'get'|GridAction|null
     */
    private function extractAction(array $filters): string|GridAction|null
    {
        return isset($filters['action']) ? GridAction::tryFrom($filters['action']) : GridAction::GET_ALL;
    }

    /**
     * extract global search filter
     * ?search=<string>
     */
    private function extractGlobalSearchFilters(array $filters): ?string
    {
        return $filters['search'] ?? null;
    }

    /**
     * extract global search filter
     * ?primaryKey=<string|string[]|array<string[]>>
     *
     * @return string|string[]|array<string[]>|null
     */
    private function extractPrimaryKey(array $filters): string|array|null
    {
        return $filters['primaryKey'] ?? null;
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
     * extract columns search filters
     * ?columns[<string>][operator]=<(<|<=|=|!=|>|>=|like)>&columns[<string>][value]=<unknown>
     */
    private function extractColumnsFilters(array $filters): ?array
    {
        $column_filters = $filters['filters'] ?? null;
        if ($column_filters) {
            $this->matchCorrectFilterData($column_filters, '=');
        }

        return $column_filters;
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
     * @return void
     */
    private function matchCorrectPath(array &$list, string $entityName, ?string $fieldName = null)
    {
        foreach ($list as &$value) {
            if ($fieldName) {
                if (is_array($value)) {
                    throw new \BadMethodCallException('Value is not an array');
                }
                if (!Str::startsWith($value[$fieldName], $entityName . '.')) {
                    $value[$fieldName] = $entityName . '.' . $value[$fieldName];
                }
            } else {
                if (!Str::startsWith($value, $entityName . '.')) {
                    $value = $entityName . '.' . $value;
                }
            }
        }
    }

    /**
     * extract columns' search filters
     * ?columns[]=name
     */
    private function extractColumns(array $filters, string $entityName): ?array
    {
        $columns = $filters['columns'] ?? null;
        if ($columns) {
            $this->matchCorrectPath($columns, $entityName);
        }

        return $columns;
    }

    /**
     * extract relations
     * ?relations[]=name
     *
     * @return array|null;
     */
    private function extractRelations(array $filters, string $entityName): ?array
    {
        $relations = $filters['relations'] ?? null;
        if ($relations) {
            $this->matchCorrectPath($relations, $entityName);
        }

        return $relations;
    }

    /**
     * extract relations
     * ?changes[0][row][id]=1&changes[0][property][name]=name&changes[0][property]=name&changes[0][value]=value
     *
     * @return array|null;
     */
    private function extractChanges(array $filters/*, string $entityName*/): ?array
    {
        $changes = $filters['changes'] ?? null;

        return $changes;
    }

    /**
     * extract columns' search filters
     * ?order[][0]=<string>&order[][1]=<asc|desc>
     */
    private function extractOrdersFilters(array $filters): ?array
    {
        return $filters['sort'] ?? null;
    }

    /**
     * replace "." with "_" in primary key name because of PHP automatic replacement in query params
     *
     * @param  string|string[]  $primaryKeyName
     * @return string|string[]
     */
    private static function replacePrimaryKeyUnderscores(string|array $primaryKeyName): array|string
    {
        return is_string($primaryKeyName) ? str_replace('.', '_', $primaryKeyName) : array_map(fn ($key) => (string) static::replacePrimaryKeyUnderscores($key), $primaryKeyName);
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
        /** @var string[] */
        $replaced = static::replacePrimaryKeyUnderscores($modelPrimaryKey);
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

    #[\Override]
    public function jsonSerialize(): mixed
    {
        $array = [];
        foreach (get_object_vars($this) as $property => $value) {
            if ($property === 'request') {
                /** @var GridRequest $value */
                $array['request'] = [
                    'uri' => str_replace('?' . $value->getQueryString(), '', $value->getRequestUri()),
                    'query' => $value->query->all(),
                    'body' => $value->request->all(),
                    'files' => $value->files->all(),
                ];
            } else {
                $array[$property] = $value ?? null;
            }
        }

        return $array;
    }

    public function __toString()
    {
        return json_encode($this);
    }
}
