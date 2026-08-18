<?php declare(strict_types=1);

namespace App\Support\Treasury;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates a Treasury table with the native database CHECK constraints that
 * GAP-037 v17's schema requires, per GAP-038 Gate 2 (Option B, Owner
 * APPROVED): the database itself must reject a violating row regardless of
 * write path (Eloquent, bulk insert, raw SQL, tinker) -- EnforcesRowInvariants
 * remains as defense-in-depth, not as the authoritative guarantee.
 *
 * MySQL supports CHECK constraints natively since 8.0.16 (this repo's
 * CI/production target is `mysql:8.0`), added via ALTER TABLE after
 * Schema::create() -- no ordering hazard, unlike composite-FK-vs-unique
 * ordering (see the migration this class's sibling code already fixed).
 *
 * SQLite cannot ALTER TABLE ADD CONSTRAINT CHECK after creation, but a
 * CHECK clause CAN be part of the table's *initial* CREATE TABLE statement
 * (every affected table here is newly created, never altered, so this is
 * always available). Laravel's Blueprint has no fluent check() builder on
 * any driver (verified directly against vendor/laravel/framework -- no
 * grammar class anywhere compiles a CHECK clause), so the approved-literal
 * DDL is produced here without hand-duplicating column/FK/index
 * definitions (which would risk silent drift from what the migration
 * closure actually declares): the SAME closure Schema::create() would have
 * received is run through a real Blueprint to get Laravel's own compiled
 * `create table (...)` SQL string via Blueprint::toSql() -- byte-identical
 * to what Schema::create() would have executed -- and only that one
 * string is text-spliced to insert `, CONSTRAINT ... CHECK (...)` clauses
 * immediately before its closing parenthesis, before executing every
 * statement Blueprint::toSql() returned (create table, then any
 * unique/index statements, in the same order Blueprint::build() itself
 * uses). Columns, foreign keys, the primary key, and every other command
 * are Laravel's own untouched compiler output.
 */
final class TreasuryCheckConstraint
{
    /**
     * @param \Closure(Blueprint):void $definition Identical to what would be
     *   passed to Schema::create() -- columns, foreign keys, indexes.
     * @param array<string, string> $checks constraint name => boolean SQL
     *   expression using bare column names, valid as a CHECK(...) body on
     *   both MySQL 8 and SQLite (this repository's two supported drivers).
     *   The exact same expression is used verbatim on both engines.
     */
    public static function createTableWithChecks(string $table, \Closure $definition, array $checks): void
    {
        if (DB::getDriverName() === 'sqlite') {
            self::createSqliteTableWithInlineChecks($table, $definition, $checks);

            return;
        }

        Schema::create($table, $definition);

        foreach ($checks as $name => $expression) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` CHECK (%s)',
                $table,
                $name,
                $expression
            ));
        }
    }

    /**
     * @param \Closure(Blueprint):void $definition
     * @param array<string, string> $checks
     */
    private static function createSqliteTableWithInlineChecks(string $table, \Closure $definition, array $checks): void
    {
        $connection = Schema::getConnection();

        $blueprint = new Blueprint($connection, $table);
        $blueprint->create();
        $definition($blueprint);

        $checkClauses = [];
        foreach ($checks as $name => $expression) {
            $checkClauses[] = sprintf('CONSTRAINT "%s" CHECK (%s)', $name, $expression);
        }
        $checkSql = $checkClauses === [] ? '' : ', '.implode(', ', $checkClauses);

        foreach ($blueprint->toSql() as $statement) {
            if (str_starts_with($statement, 'create table') && $checkSql !== '') {
                if (!str_ends_with($statement, ')')) {
                    throw new \LogicException(
                        "Unexpected SQLite create-table statement shape for `{$table}` -- ".
                        'refusing to splice CHECK clauses into an unrecognized statement: '.$statement
                    );
                }

                $statement = substr($statement, 0, -1).$checkSql.')';
            }

            $connection->statement($statement);
        }
    }
}
