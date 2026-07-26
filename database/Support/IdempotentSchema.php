<?php

namespace Database\Support;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guards for MySQL migrations that may be retried after a partial failure
 * (DDL auto-commits per statement; Laravel only records the batch when up() finishes).
 */
final class IdempotentSchema
{
    public static function create(string $table, Closure $callback): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $callback);
        }
    }

    /**
     * Run Schema::table only when at least one of the given columns is missing.
     * Prefer checking individual columns inside the callback when adding a mix of columns.
     *
     * @param  list<string>  $columns
     */
    public static function tableIfMissingColumns(string $table, array $columns, Closure $callback): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, $callback);

                return;
            }
        }
    }

    public static function tableIfHasColumn(string $table, string $column, Closure $callback): void
    {
        if (Schema::hasColumn($table, $column)) {
            Schema::table($table, $callback);
        }
    }

    public static function dropConstrainedForeignIdIfExists(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropConstrainedForeignId($column);
        });
    }

    public static function dropColumnsIfExist(string $table, string ...$columns): void
    {
        $existing = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column),
        ));

        if ($existing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($existing): void {
            $blueprint->dropColumn($existing);
        });
    }

    public static function hasIndex(string $table, string|array $index): bool
    {
        return Schema::hasIndex($table, $index);
    }

    /**
     * @param  Closure(Blueprint): void  $callback
     */
    public static function tableIfMissingIndex(string $table, string|array $index, Closure $callback): void
    {
        if (! Schema::hasIndex($table, $index)) {
            Schema::table($table, $callback);
        }
    }

    public static function hasForeignKey(string $table, string $column): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return false;
        }

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            $columns = $foreignKey['columns'] ?? [];

            if (in_array($column, $columns, true)) {
                return true;
            }
        }

        return false;
    }

    public static function dropForeignKeyIfExists(string $table, string $column): void
    {
        if (! self::hasForeignKey($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropForeign([$column]);
        });
    }
}
