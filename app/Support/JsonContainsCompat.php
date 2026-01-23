<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

final class JsonContainsCompat
{
    /**
     * Apply JSON containment conditions with a SQLite-safe fallback.
     *
     * @param Builder $query   Query builder instance.
     * @param string  $column  JSON column to inspect.
     * @param array|string $values Single value or list of values that must exist.
     *
     * @return Builder
     */
    public static function apply(Builder $query, string $column, array|string $values): Builder
    {
        if ($query->getConnection()->getDriverName() !== 'sqlite') {
            return $query->whereJsonContains($column, $values);
        }

        $grammar = $query->getGrammar();
        $columnExpression = $grammar->wrap($column);

        foreach (Arr::wrap($values) as $value) {
            $query->whereRaw(
                "EXISTS (SELECT 1 FROM json_each({$columnExpression}) WHERE value = ?)",
                [$value]
            );
        }

        return $query;
    }
}
