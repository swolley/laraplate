<?php

declare(strict_types=1);

namespace Modules\Core\Inspector;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Inspector\Entities\Index;
use Modules\Core\Inspector\Entities\Table;
use Modules\Core\Inspector\Entities\Column;
use Modules\Core\Inspector\Entities\ForeignKey;

class Inspect
{
    /**
     * Retrieve a particular table for a given database connection.
     */
    public static function table(string $name, null|string $schema = null): ?Table
    {
        $key_name = ($schema ?? 'default') . '_' . $name;
        if ($inspected_data = Cache::tags(['inspector', $schema ?? 'default'])->get($key_name)) {
            return $inspected_data;
        }

        /** @phpstan-ignore staticMethod.notFound */
        $connection = Schema::connection($schema);
        $tables = $connection->getTables();
        $table = Arr::first($tables, fn($table) => $table['name'] === $name);

        if (!$table) {
            return null;
        }

        $database_name = $connection->getConnection()->getDatabaseName();
        $columns = self::parseColumns($connection->getColumns($table['name']));
        $indexes = self::parseIndexes($connection->getIndexes($table['name']));
        $foreignKeys = self::parseForeignKeys($connection->getForeignKeys($table['name']), $database_name, $schema);

        $inspected_data =  new Table(
            $table['name'],
            $columns,
            $indexes,
            $foreignKeys,
            $database_name,
            $schema,
        );

        Cache::tags(['inspector', $schema])->forever($key_name, $inspected_data);

        return $inspected_data;
    }

    /**
     * Retrieve the columns of a particular table for a given database connection.
     */
    public static function columns(string $table, null|string $schema = null): Collection
    {
        if ($table = self::table($table, $schema)) {
            return $table->columns;
        }

        return new Collection();
    }

    /**
     * Retrieve the indexes of a particular table for a given database connection.
     */
    public static function indexes(string $table, null|string $schema = null): Collection
    {
        if ($table = self::table($table, $schema)) {
            return $table->indexes;
        }

        return new Collection();
    }

    /**
     * Retrieve the foreign keys of a particular table for a given database connection.
     */
    public static function foreignKeys(string $table, null|string $schema = null): Collection
    {
        if ($table = self::table($table, $schema)) {
            return $table->foreignKeys;
        }

        return new Collection();
    }

    /**
     * Retrieve a particular column of a particular table for a given
     * database connection.
     *
     */
    public static function column(string $name, string $table, null|string $schema = null): ?Column
    {
        $columns = self::columns($table, $schema);

        return Arr::first($columns, fn($column) => $column['name'] === $name);
    }

    /**
     * Retrieve a particular index of a particular table for a given
     * database connection.
     *
     */
    public static function index(string $name, string $table, null|string $schema = null): ?Index
    {
        $indexes = self::indexes($table, $schema);

        return Arr::first($indexes, fn($index) => $index['name'] === $name);
    }

    /**
     * Retrieve a particular foreign key of a particular table for a given
     * database connection.
     *
     */
    public static function foreignKey(string $name, string $table, null|string $schema = null): ?ForeignKey
    {
        $foreigns = self::foreignKeys($table, $schema);

        return Arr::first($foreigns, fn($foreign) => $foreign['name'] === $name);
    }

    private static function getAttributesForColumn(array $column): Collection
    {
        return collect([
            $column['type_name'],
            $column['auto_increment'] ? 'autoincrement' : null,
            $column['nullable'] ? 'nullable' : null,
            $column['collation'],
        ])->filter();
    }

    /**
     *
     * @return Collection<Column>
     */
    private static function parseColumns(array $columns): Collection
    {
        return collect($columns)->map(fn($column) => new Column(
            $column['name'],
            self::getAttributesForColumn($column),
            $column['default'],
            $column['type'],
        ));
    }

    private static function getAttributesForIndex(array $index): Collection
    {
        return collect([
            $index['type'],
            count($index['columns']) > 1 ? 'compound' : null,
            $index['unique'] && !$index['primary'] ? 'unique' : null,
            $index['primary'] ? 'primary' : null,
        ])->filter();
    }

    /**
     *
     * @return Collection<Index>
     */
    private static function parseIndexes(array $indexes): Collection
    {
        return collect($indexes)->map(fn($index) => new Index(
            $index['name'],
            collect($index['columns']),
            self::getAttributesForIndex($index),
        ));
    }

    /**
     * @return Collection<ForeignKey>
     */
    private static function parseForeignKeys(array $keys, string $schema, ?string $connection = null): Collection
    {
        return collect($keys)->map(fn($foreignKey) => new ForeignKey(
            $foreignKey['name'],
            collect($foreignKey['columns']),
            $foreignKey['foreign_schema'],
            $foreignKey['foreign_table'],
            collect($foreignKey['foreign_columns']),
            $schema,
            $connection,
            $foreignKey['on_update'],
            $foreignKey['on_delete'],
        ));
    }
}
