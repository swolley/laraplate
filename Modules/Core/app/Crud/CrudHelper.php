<?php

declare(strict_types=1);

namespace Modules\Core\Crud;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Core\Casts\Sort;
use InvalidArgumentException;
use Modules\Core\Casts\Column;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\ColumnType;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Inspector\Inspect;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\FilterOperator;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\ListRequestData;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Casts\SelectRequestData;
use Illuminate\Database\Eloquent\Relations\Relation;

class CrudHelper
{
	/**
	 * @throws InvalidArgumentException
	 */
	public function prepareQuery(Builder $query, SelectRequestData $request_data): void
	{
		$main_model = $query->getModel();
		$main_entity = $main_model->getTable();
		$relations_sorts = [];
		$relations_columns = [];
		$relations_filters = [];

		$columns = self::groupColumns($main_entity, $request_data->columns);
		foreach ($columns as $type => $cols) {
			if ($type === 'main' && !empty($cols)) {
				self::sortColumns($query, $cols);
				$only_standard_columns = [];
				foreach ($cols as $column) {
					if ($column->type === ColumnType::COLUMN) {
						$only_standard_columns[] = $column->name;
					}
				}
				// TODO: qui mancano ancora le colonne utili a fare le relation se la foreign key si trova sulla main table
				$this->addForeignKeysToSelectedColumns($query, $only_standard_columns, $main_model, $main_entity);
				$query->select($only_standard_columns);
			} else if ($type === 'relations' && !empty($cols)) {
				foreach ($cols as $relation => $relation_cols) {
					self::sortColumns($query, $relation_cols);
					$only_relation_columns = [];
					foreach ($relation_cols as $column) {
						if ($column->type === ColumnType::COLUMN) {
							$only_relation_columns[] = $column;
						}
					}
					$relations_columns[$relation] = $only_relation_columns;
					if (!in_array($relation, $request_data->relations)) {
						$request_data->relations[] = $relation;
					}
				}
			}
		}

		if ($request_data instanceof ListRequestData) {
			// check for sorts and prepare data
			if (isset($request_data->sort)) {
				foreach ($request_data->sort as $column) {
					if (preg_match("/^\w+\.\w+$/", $column->property)) {
						$query->orderBy($column->property, $column->direction->value);
					} else {
						$index = str_replace($main_entity . '.', '', $column->property);
						$splitted = self::splitColumnNameOnLastDot($index);
						$cloned_column = new Sort($splitted[1], $column->direction);
						if (!array_key_exists($index, $columns['relations'])) {
							$relations_sorts[$splitted[0]] = [$cloned_column];
						} else {
							$relations_sorts[$splitted[0]][] = $cloned_column;
						}
					}
				}
			}
			// if (isset($request_data->group_by)) {
			//     $request_data->group_by = array_map(fn (string $group) => str_replace($main_entity . '.', '', $group), $request_data->group_by);
			// }
		}

		if ($request_data instanceof ListRequestData && isset($request_data->filters)) {
			// TODO: come faccio a smontare filters e raggrupparlo per la singola relation?
			// forse devo fare un filter ricorsivo nell'oggetto FiltersGroup e tirare fuori solo i campi relativi alla singoal relation o sottorelation conservando la struttura originale?

			// foreach ($request_data->filters->filters as $filter) {
			//     if (!preg_match("/^\w+\.\w+$/", $filter->property)) {
			//         $index = str_replace($main_entity . '.', '', $filter->property);
			//         $splitted = self::splitColumnNameOnLastDot($index);
			//         $cloned_filter = new Filter($splitted[1], $filter->value, $filter->operator);
			//         $relation_name = preg_replace('/\.' . $splitted[1] . '$/', '', $filter->property);
			//         if (!array_key_exists($index, $relations_filters[$relation_name])) {
			//             $relations_filters[$relation_name] = [$cloned_filter];
			//         } else {
			//             $relations_filters[$relation_name][] = $cloned_filter;
			//         }
			//     }
			// }

			$this->recursivelyApplyFilters($query, $request_data->filters, $columns['relations']);
		}

		if (!empty($request_data->relations)) {
			$this->applyRelations($query, $request_data->relations, $relations_columns, $relations_sorts, $columns['aggregates'], $relations_filters);
		}
	}


	/**
	 * @return non-empty-array<array-key, string>
	 */
	private static function splitColumnNameOnLastDot(string $name): array
	{
		return preg_split('/\.(?=[^.]*$)/', $name, 2);
	}

	/** @return array{main: Column[], relations: array<string, Column[]>, aggregates: array<string, Column[]>} */
	private static function groupColumns(string &$mainEntity, array $columns_filters): array
	{
		$columns = [
			'main' => [],
			'relations' => [],
			'aggregates' => [],
		];

		if (!empty($columns_filters)) {
			// used only for quick search instead of array_filter
			/** @var string[] $all_relations_names */
			$all_relations_names = [];

			/** @var object{name: string, type: ColumnType} $column */
			foreach ($columns_filters as $column) {
				$index = str_replace($mainEntity . '.', '', $column->name);
				if (preg_match("/^\w+\.\w+$/", $column->name) && $column->type === ColumnType::COLUMN) {
					$columns['main'][] = new Column($index, $column->type);
				} else {
					$splitted = self::splitColumnNameOnLastDot($index);
					if (!isset($splitted[1])) {
						$splitted[1] = '*';
					}
					if ($column->type === ColumnType::COLUMN) {
						$remapped_column = new Column($splitted[1], $column->type);
						if (!in_array($splitted[0], $all_relations_names)) {
							$columns['relations'][$splitted[0]] = [$remapped_column];
							$all_relations_names[] = $splitted[0];
						} else {
							$columns['relations'][$splitted[0]][] = $remapped_column;
						}
					} else if ($column->type->isAggregateColumn()) {
						$cloned_column = new Column($splitted[1], $column->type);
						if (!array_key_exists($index, $columns['aggregates'])) {
							$columns['aggregates'][$splitted[0]] = [$cloned_column];
						} else {
							$columns['aggregates'][$splitted[0]][] = $cloned_column;
						}
					}
				}
			}
		}

		return $columns;
	}

	/**
	 * removes unnecessary unnecessary relationships
	 */
	private function cleanRelations(array &$relations): void
	{
		// tengo parent commentato per ricordarmi che non va aggiunto
		$black_list = [
			'history',
			'ancestors',
			'ancestorsAndSelf',
			'bloodline',
			'children',
			'childrenAndSelf',
			'descendants',
			'descendantsAndSelf',
			/*, 'parent,'*/
			'parentAndSelf',
			'rootAncestor',
			'siblings',
			'siblingsAndSelf'
		];
		$relations = array_filter($relations, fn($relation) => !in_array($relation, $black_list));
	}

	/**
	 * @return (mixed|null|string)[]
	 *
	 * @psalm-return array{relation: string, connection: 'default'|mixed, table: mixed, field: null|string}
	 */
	private function splitProperty(Builder|Model $model, string $property): array
	{
		/** @var string[] $exploded */
		$exploded = explode('.', $property);
		if (!empty($exploded) && $exploded[0] === $model->getTable()) {
			array_shift($exploded);
		}
		$field = array_pop($exploded);
		$relation = implode('.', $exploded);
		$relation_model = $model instanceof Model ? $model : $model->getModel();
		array_shift($exploded);
		while (!empty($exploded)) {
			$relation_model = $relation_model->{array_shift($exploded)}()->getModel();
		}

		return [
			'relation' => $relation,
			'connection' => $relation_model->connection ?? 'default',
			'table' => $relation_model->getTable(),
			'field' => $field,
		];
	}

	private function applyFilter(Builder $query, Filter $filter, string &$method, array &$relations_columns): void
	{
		if (substr_count($filter->property, '.') > 1) {
			// relations
			$splitted = $this->splitProperty($query->getModel(), $filter->property);
			$query->{$method . 'Has'}($splitted['relation'], function (Builder $q) use ($filter, $method, $splitted, $relations_columns) {
				if ($splitted['field'] === 'deleted_at') {
					$permission = $splitted['connection'] . '.' . $splitted['table'] . '.' . 'delete';
					$user = Auth::user();
					if ($user->can($permission)) {
						$q->withTrashed();
					}
				}
				$cloned_filter = new Filter($splitted['field'], $filter->value, $filter->operator);
				$this->applyFilter($q, $cloned_filter, $method, $relations_columns);
			});
		} elseif ($filter->value == null) {
			// is or is not null
			$method = $method . ($filter->operator === FilterOperator::EQUALS ? 'Null' : 'NotNull');
			$query->$method($filter->property);
		} elseif (in_array($filter->operator, [FilterOperator::LIKE, FilterOperator::NOT_LIKE])) {
			// like not like
			$method = $method . Str::studly($filter->operator->value);
			$query->$method($filter->property, $filter->value);
		} else {
			// all the others
			$query->$method($filter->property, $filter->operator->value, $filter->value);
		}
	}

	private function recursivelyApplyFilters(Builder|Relation $query, FiltersGroup|array $filters, array $relation_columns): void
	{
		$iterable = is_array($filters) && Arr::isList($filters) ? $filters : $filters->filters;
		$method = $filters->operator === WhereClause::AND ? 'where' : 'orWhere';
		foreach ($iterable as &$subfilter) {
			if (isset($subfilter->filters)) {
				$query->$method(fn(Builder $q) => $this->recursivelyApplyFilters($q, $subfilter, $relation_columns));
			} else {
				$this->applyFilter($query, $subfilter, $method, $relation_columns);
			}
		}
	}

	/**
	 * @param  Column[]  $columns
	 * @return void
	 */
	private static function sortColumns(Builder|Relation $query, array &$columns)
	{
		usort($columns, fn(Column $a, Column $b) => $a->name <=> $b->name);
		$all_columns_name = array_map(fn(Column $column) => $column->name, $columns);
		$primary_key = Arr::wrap($query->getModel()->getKeyName());
		foreach ($primary_key as $key) {
			if (!in_array($key, $all_columns_name)) {
				array_unshift($columns, new Column($key, ColumnType::COLUMN));
				$all_columns_name[] = $key;
			}
		}
	}

	/**
	 * @param Builder|Relation $query
	 * @param Column[] $relation_columns
	 */
	private function applyColumnsToSelect(Builder|Relation $query, array &$relation_columns)
	{
		self::sortColumns($query, $relation_columns);
		$simple_columns = [];
		foreach ($relation_columns as $column) {
			if ($column->type === ColumnType::COLUMN) {
				$simple_columns[] = $column->name;
			}
		}
		$query->select($simple_columns);
	}

	/**
	 * apply only direct aggregate relations on the current related entity
	 */
	private function applyAggregatesToQuery(Builder|Relation $query, array &$relations_aggregates, string $relation)
	{
		foreach ($relations_aggregates as $aggregate_relation => $aggregates_cols) {
			$escaped = preg_quote($relation);
			if (preg_match('/^' . $escaped . '\.\w+$/', $aggregate_relation) !== 1) continue;

			$subrelation = preg_replace('/^' . $escaped . '\./', '', $aggregate_relation);
			foreach ($aggregates_cols as $col) {
				$method = 'with' . ucfirst($col->type->value);
				if ($col->type === ColumnType::SUM || $col->type === ColumnType::COUNT) {
					$query->$method([$subrelation]);
				} else {
					$query->$method([$subrelation . '.' . $col->name]);
				}
			}
			unset($relations_aggregates[$aggregate_relation]);
		}
	}

	private function addForeignKeysToSelectedColumns(Builder|Relation $query, array &$selectColumns, Model $model = null, string $table = null)
	{
		if (!$model) {
			$model = $query->getModel();
		}
		if (!$table) {
			$table = $model->getTable();
		}
		foreach (Inspect::foreignKeys($table, $model->getConnection()->getName()) as $foreign) {
			foreach ($foreign->columns as $column) {
				$selectColumns[] = new Column($column);
			}
		}
	}

	private function createRelationCallback(Relation $query, string $relation, array &$relations_columns, array &$relations_sorts, array &$relations_aggregates, array &$relations_filters): void
	{
		if (!empty($relations_columns[$relation])) {
			$this->addForeignKeysToSelectedColumns($query, $relations_columns[$relation]);
			$this->applyColumnsToSelect($query, $relations_columns[$relation]);
		}

		$this->applyAggregatesToQuery($query, $relations_aggregates, $relation);

		if (isset($relations_filters[$relation])) {
			$this->recursivelyApplyFilters($query, $relations_filters[$relation], $relations_columns[$relation]);
		}

		if (!empty($relations_sorts[$relation])) {
			foreach ($relations_sorts[$relation] as $sort) {
				$query->orderBy($sort->property, $sort->direction->value);
			}
		}
	}

	/**
	 * @param  string[]  $relations
	 * @param  array<array-key, Column[]>  $relations_columns
	 * @param  array<string, Sort[]>  $relations_sorts
	 */
	private function applyRelations(Builder $query, array $relations, array &$relations_columns, array &$relations_sorts, array &$relations_aggregates, array &$relations_filters): void
	{
		$merged_relations = array_unique(array_merge($relations, array_keys($relations_sorts), array_keys($relations_columns)));
		$this->cleanRelations($relations);

		// apply only direct aggregate relations on the main entity
		foreach ($relations_aggregates as $relation => $aggregates_cols) {
			if (Str::contains($relation, '.')) continue;

			foreach ($aggregates_cols as $col) {
				$method = 'with' . ucfirst($col->type->value);
				if ($col->type === ColumnType::SUM || $col->type === ColumnType::COUNT) {
					$query->$method([$relation]);
				} else {
					$query->$method([$relation . '.' . $col->name]);
				}
			}
			unset($relations_aggregates[$relation]);
		}

		$withs = [];
		foreach ($merged_relations as $relation) {
			$withs[$relation] = function (Relation $q) use ($relation, $relations_columns, $relations_sorts, $relations_aggregates, $relations_filters) {
				$this->createRelationCallback($q, $relation, $relations_columns, $relations_sorts, $relations_aggregates, $relations_filters);
			};
		}
		$query->with($withs);
	}
}
