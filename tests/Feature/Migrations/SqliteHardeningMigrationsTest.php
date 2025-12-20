<?php

namespace Tests\Feature\Migrations;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;

class SqliteHardeningMigrationsTest extends TestCase
{
    /** @test */
    public function sqlite_hardening_migrations_run_without_mysql_logic(): void
    {
        $this->assertSame('sqlite', DB::getDriverName());

        $captured = $this->captureQueriesDuring(fn () => Artisan::call('migrate:fresh --force'));

        $this->assertSame(0, $captured['exitCode']);
        $this->assertNoMysqlOnlySql($captured['queries']);
    }

    /**
     * Run the action while capturing executed SQL statements.
     */
    private function captureQueriesDuring(callable $callback): array
    {
        $queries = [];
        $recording = true;

        DB::listen(function (QueryExecuted $event) use (&$queries, &$recording) {
            if (!$recording) {
                return;
            }

            $queries[] = $event->sql;
        });

        $exitCode = $callback();

        $recording = false;

        return compact('exitCode', 'queries');
    }

    /**
     * Assert that no recorded SQL contains MySQL-only syntax.
     */
    private function assertNoMysqlOnlySql(array $queries): void
    {
        $violations = [];

        foreach ($queries as $sql) {
            $normalized = $this->normalizeSql($sql);

            if ($reason = $this->detectForbiddenSql($normalized)) {
                $violations[] = "{$sql} ({$reason})";
            }
        }

        $this->assertEmpty(
            $violations,
            'MySQL-only SQL detected during migrations:' . "\n" . implode("\n", $violations)
        );
    }

    private function normalizeSql(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($sql)));
    }

    private function detectForbiddenSql(string $normalizedSql): ?string
    {
        $checkList = [
            'information_schema' => 'information_schema query',
            'engine=innodb' => 'ENGINE=InnoDB clause',
            'add fulltext' => 'ADD FULLTEXT clause',
            'generated always' => 'GENERATED ALWAYS expression',
            'stored ' => 'STORED generated column',
            'virtual ' => 'VIRTUAL generated column',
            'algorithm=' => 'ALGORITHM clause',
            'lock=' => 'LOCK clause',
            'using btree' => 'USING BTREE clause',
            'show index' => 'SHOW INDEX command',
            'show full columns' => 'SHOW FULL COLUMNS command',
        ];

        foreach ($checkList as $needle => $reason) {
            if (str_contains($normalizedSql, $needle)) {
                return $reason;
            }
        }

        if (str_contains($normalizedSql, 'alter table') &&
            preg_match('/engine=|algorithm=|lock=/', $normalizedSql)) {
            return 'ALTER TABLE with MySQL-only clauses';
        }

        return null;
    }
}
