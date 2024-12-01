<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Illuminate\Support\Str;
use Modules\Core\Http\Requests\SelectRequest;

class SelectRequestData extends CrudRequestData
{
    /**
     * @var Column[]
     */
    public array $columns;

    /**
     * @var string[]
     */
    public array $relations;

    /**
     * @param string|string[] $primaryKey
     */
    public function __construct(SelectRequest $request, string $mainEntity, array $validated, string|array $primaryKey)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey);
        $this->columns = $this->conformColumns($validated['columns'] ?? []);
        $this->relations = $this->conformRelations($validated['relations'] ?? []);
    }

    private function conformColumnName(string $column): string
    {
        if (!Str::startsWith($column, $this->mainEntity)) {
            return $this->mainEntity . '.' . $column;
        }

        return $column;
    }

    /**
     *
     * @return Column[]
     */
    private function conformColumns(array $columns): array
    {
        foreach ($columns as &$column) {
            if (is_string($column)) {
                $column = new Column($this->conformColumnName($column));
            } else {
                $column = new Column($this->conformColumnName($column['name']), $column['type']);
            }
        }

        return $columns;
    }

    /**
     *
     * @return string[]
     */
    private function conformRelations(array $relations): array
    {
        foreach ($relations as &$relation) {
            if (Str::startsWith($relation, $this->mainEntity)) {
                $relation = str_replace($this->mainEntity . '.', '', $relation);
            }
        }

        return $relations;
    }
}
