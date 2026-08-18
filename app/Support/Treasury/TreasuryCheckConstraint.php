<?php declare(strict_types=1);

namespace App\Support\Treasury;

use Illuminate\Support\Facades\DB;

/**
 * Adds a native, database-engine-enforced CHECK-equivalent constraint to a
 * Treasury table, per GAP-038 Gate 2 (Option B): the database itself must
 * reject a violating row regardless of write path (Eloquent, bulk insert,
 * raw SQL, tinker) -- EnforcesRowInvariants remains as defense-in-depth,
 * not as the authoritative guarantee.
 *
 * MySQL supports CHECK constraints natively since 8.0.16 (this repo's
 * CI/production target is `mysql:8.0`), added here via ALTER TABLE after
 * Schema::create() -- no ordering hazard, unlike composite-FK-vs-unique
 * ordering (see the migration this trait's sibling code already fixed).
 *
 * SQLite cannot ALTER TABLE ADD CONSTRAINT CHECK after creation. A pair of
 * BEFORE INSERT / BEFORE UPDATE triggers that RAISE(ABORT, ...) on
 * violation is the semantically equivalent native mechanism: it is
 * DB-engine-enforced, not application code, and is not bypassable by a raw
 * SQL write any more than a real CHECK constraint would be. SQLite
 * automatically drops a table's triggers when the table itself is dropped,
 * so no explicit teardown is needed beyond the existing
 * Schema::dropIfExists() in each migration's down().
 */
final class TreasuryCheckConstraint
{
    /**
     * @param string $mysqlExpression Boolean SQL expression using bare column
     *   names, valid inside `CHECK (...)` on MySQL 8.
     * @param string $sqliteWhenExpression The same boolean condition, with
     *   columns qualified as `NEW.<column>` for use in a trigger's WHEN
     *   clause. Kept as an explicit, separate, human-reviewable expression
     *   (not auto-derived from $mysqlExpression) so both driver's exact
     *   enforced condition is visible side by side in the migration.
     */
    public static function add(
        string $table,
        string $name,
        string $mysqlExpression,
        string $sqliteWhenExpression
    ): void {
        if (DB::getDriverName() === 'sqlite') {
            self::addSqliteTriggers($table, $name, $sqliteWhenExpression);

            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` CHECK (%s)',
            $table,
            $name,
            $mysqlExpression
        ));
    }

    private static function addSqliteTriggers(string $table, string $name, string $whenExpression): void
    {
        foreach (['ins' => 'INSERT', 'upd' => 'UPDATE'] as $suffix => $event) {
            DB::statement(sprintf(
                "CREATE TRIGGER %s_%s BEFORE %s ON %s
                 WHEN NOT (%s)
                 BEGIN SELECT RAISE(ABORT, '%s'); END",
                $name,
                $suffix,
                $event,
                $table,
                $whenExpression,
                addslashes("{$name}: CHECK constraint violated")
            ));
        }
    }
}
