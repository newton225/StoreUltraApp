<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use PDO;

/**
 * The one way the server searches text (playbook DATA-13): the match ignores case AND accents, so « ete » finds
 * « Été » and « eleonore » finds « Éléonore ». PostgreSQL uses its `unaccent` extension (enabled by a migration);
 * SQLite, used by the tests and by a developer machine, gets an `unaccent` function registered on the connection.
 * Never write a direct `like` on a name or a reference.
 *
 * The term is looked for anywhere in the column. `%`, `_` and `\` typed by the user are plain characters.
 */
class Search
{
    /**
     * Add « the column contains the term » to the query, with `and` or, when $or is true, with `or`.
     *
     * @param  EloquentBuilder<*>|QueryBuilder  $query
     * @param  literal-string  $column  a plain column name written in the code, never typed by a user
     */
    public static function contains(EloquentBuilder|QueryBuilder $query, string $column, string $term, bool $or = false): void
    {
        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;
        $connection = $base->getConnection();
        $sqlite = $connection instanceof Connection && $connection->getDriverName() === 'sqlite';

        if ($sqlite) {
            self::registerSqliteFunction($connection);
        }

        $pattern = '%'.addcslashes($term, '%_\\').'%';

        // PostgreSQL already escapes with a backslash, and its PDO driver misreads a quoted backslash as an escaped
        // quote (it would swallow the placeholder); SQLite has no default escape character and needs the clause.
        $escape = $sqlite ? " escape '\\'" : '';

        $query->whereRaw(
            new Expression("lower(unaccent(\"{$column}\")) like lower(unaccent(cast(? as text))){$escape}"),
            [$pattern],
            $or ? 'or' : 'and',
        );
    }

    /**
     * Order by a text column the way a person reads it: without caring for accents or case, so « Éléonore » sits
     * between « Eleonore » and « Eloi », not after « Zadi ».
     *
     * @param  EloquentBuilder<*>|QueryBuilder  $query
     * @param  literal-string  $column  a plain column name written in the code, never typed by a user
     */
    public static function orderBy(EloquentBuilder|QueryBuilder $query, string $column): void
    {
        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;
        $connection = $base->getConnection();

        if ($connection instanceof Connection && $connection->getDriverName() === 'sqlite') {
            self::registerSqliteFunction($connection);
        }

        $query->orderByRaw("lower(unaccent(\"{$column}\"))");
    }

    /**
     * Give SQLite the `unaccent` function PostgreSQL has: letters without their marks, as plain ASCII.
     */
    private static function registerSqliteFunction(Connection $connection): void
    {
        $pdo = $connection->getPdo();

        $pdo->sqliteCreateFunction(
            'unaccent',
            fn (mixed $value): ?string => $value === null ? null : Str::ascii((string) $value),
            1,
            PDO::SQLITE_DETERMINISTIC,
        );
    }
}
