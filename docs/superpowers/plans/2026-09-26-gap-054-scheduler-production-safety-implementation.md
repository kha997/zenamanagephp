---
work_id: GAP-054
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-054/02-design.md
---

# GAP-054 Scheduler & backup production-safety implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every workload the scheduler registers safe to enable in
production — only real commands, no whole-cache flush, no secrets or
command-line passwords in backups, per-type retention, configured storage —
exactly as approved in Gate 2.

**Architecture:** Two small, focused classes under `app/Services/Backup/`
carry the new behavior: `MysqlClient` (credential-safe `mysqldump`/`mysql`
execution through a `0600` option file and argument arrays) and
`BackupArchiveStore` (where archives live, typed naming, per-type retention,
newest-backup lookup). `BackupCommand`, `MaintenanceCommand`,
`DatabaseBackupService`, and `LaunchChecklistService` delegate to them.
`Kernel::schedule()` loses the broken/unsafe entries.

**Tech Stack:** PHP 8.2, Laravel 12.63 (`Illuminate\Support\Facades\Process`,
`Storage`, `Schedule`), PHPUnit, PHPStan level 6 (no larastan).

**Spec:** `docs/owner-decisions/GAP-054/02-design.md` (Owner-approved
2026-09-26 at PR #318 head `6933cb0a2b4e84a96e6070c12390f909a7ad3157`,
Q1–Q4 as recommended), evidence
`docs/audits/2026-09-26-gap-054-scheduler-production-safety-evidence.md`.

## Global Constraints

- Canonical base: `adacc5cc5fb8a08353cc90576076724e45e6e8bc`; single lifecycle PR #318 (Draft).
- Q1 included: remove argument-less `queue:monitor` from the schedule; no replacement monitor.
- Q2: backups never collect `.env` or `.env.*`.
- Q3 defaults: full = 30 days / 30 archives; database = 7 days / 28 archives; files/config fall back to full limits.
- Q4: `BACKUP_DISK` unset → archives stay in `storage/backups` exactly as today.
- No MySQL credential in any process argument; `--defaults-extra-file` is the first option; option file mode `0600`, deleted in `finally`.
- Legacy untyped `backup_<ts>*` artifacts are never counted or deleted by the new retention.
- Unchanged: `enable_scheduler` gate, every `withoutOverlapping()`, `maintenance:run --task=metrics|database|logs`, `queue:restart`, `backup:run` schedule times.
- Out of scope (stop and return to Owner if needed): scheduler enablement, env/cron/supervisor/compose/workflow changes, credential rotation, heartbeat, log retention, multi-node locks, Docker caching step, remote-storage providers, deleting legacy archives, GAP-049 evidence edits.
- Proof-first: every behavior test is observed FAILING on unchanged code before the fix, and the failure output is kept for the Gate-3 packet.
- PHPStan level 6 conventions: typed properties/params/returns, `@param array<string, mixed>` / `@return list<...>` generics, no larastan helpers.

---

## File structure

| File | Responsibility |
|---|---|
| Create `app/Services/Backup/MysqlClient.php` | Run `mysqldump` / `mysql` with credentials only in a temporary `0600` option file; argument arrays, no shell. |
| Create `app/Services/Backup/BackupArchiveStore.php` | Archive location (legacy dir vs configured disk), typed names, per-type retention, newest-backup timestamp, local paths a files backup must exclude. |
| Modify `app/Console/Kernel.php:21-95` | Remove broken/unsafe schedule entries. |
| Modify `app/Console/Commands/MaintenanceCommand.php` | `--task=cache` fails closed; `--task=all` skips it; `createBackup()` uses `MysqlClient`. |
| Modify `app/Console/Commands/BackupCommand.php` | Typed staging dirs, `MysqlClient`, no `.env`, exclusions, `tar` via Process array, store + per-type prune via `BackupArchiveStore`. |
| Modify `app/Services/DatabaseBackupService.php:35-450` | Full/incremental dump and restore via `MysqlClient`; `gzip`/`gunzip` as arrays. |
| Modify `app/Services/LaunchChecklistService.php:405-425` | Newest backup from `BackupArchiveStore`. |
| Modify `config/backup.php` | `disk` default `null`, per-type `retention`. |
| Modify `docs/runbooks/gap-049-host-provisioning.md`, `.env.example:34-36` | Accurate scheduler enablement guidance. |
| Tests (create) | `tests/Feature/Console/ScheduleContractTest.php`, `tests/Feature/Console/CacheMaintenanceFailClosedTest.php`, `tests/Unit/Services/Backup/MysqlClientTest.php`, `tests/Architecture/NoCommandLineDatabasePasswordTest.php`, `tests/Feature/Console/BackupContentsSafetyTest.php`, `tests/Feature/Console/BackupRetentionTest.php`, `tests/Feature/Console/BackupStorageLocationTest.php` |
| Tests (modify) | `tests/Feature/BackupCommandTest.php`, `tests/Feature/QualityAssuranceTest.php:222-235`, `tests/Feature/FinalSystemTest.php:816-829`, `tests/Unit/LaunchChecklistServiceBackupTest.php` |

---

### Task 0: Worktree test environment (no commit)

The worktree has no `vendor/`. Never run `composer` inside the worktree
(it rewrites the shared autoloader — this broke the main checkout on
2026-09-26).

- [ ] **Step 1: Provision an isolated vendor**

```bash
cd .worktrees/GAP-054-scheduler-production-safety-gate1
MAIN=/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden
mkdir vendor
for d in "$MAIN"/vendor/*; do n=$(basename "$d"); [ "$n" = composer ] || [ "$n" = bin ] || [ "$n" = autoload.php ] || ln -s "$d" "vendor/$n"; done
cp -R "$MAIN/vendor/composer" vendor/composer
cp -R "$MAIN/vendor/bin" vendor/bin
cp "$MAIN/vendor/autoload.php" vendor/autoload.php
[ -f .env ] || cp .env.example .env.testing-local-only
```

- [ ] **Step 2: Prove the autoloader resolves to the worktree**

Run: `php -r 'require "vendor/autoload.php"; echo (new ReflectionClass(App\Console\Kernel::class))->getFileName(), PHP_EOL;'`
Expected: a path inside `.worktrees/GAP-054-scheduler-production-safety-gate1/app/Console/Kernel.php`.

- [ ] **Step 3: Baseline the touched suites**

Run: `./vendor/bin/phpunit tests/Feature/BackupCommandTest.php tests/Unit/LaunchChecklistServiceBackupTest.php`
Expected: PASS (baseline). `vendor/` is git-ignored; confirm `git status --porcelain` shows no `vendor`.

---

### Task 1: Schedule registers only safe, real commands

**Files:**
- Test: `tests/Feature/Console/ScheduleContractTest.php` (create)
- Modify: `app/Console/Kernel.php:21-95`

**Interfaces:** Produces nothing consumed later.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use Tests\TestCase;

class ScheduleContractTest extends TestCase
{
    /** @return list<string> scheduled artisan command lines, e.g. "backup:run --type=all" */
    private function scheduledCommands(): array
    {
        config(['app.enable_scheduler' => true]);
        $schedule = new Schedule();
        $kernel = $this->app->make(ConsoleKernel::class);
        $method = new ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        return array_values(array_map(static function (Event $event): string {
            $command = (string) $event->command;
            $pos = strpos($command, 'artisan');
            $tail = $pos === false ? $command : substr($command, $pos + strlen('artisan'));

            return trim(str_replace(["'", '"'], '', $tail));
        }, $schedule->events()));
    }

    public function test_every_scheduled_command_is_registered(): void
    {
        $registered = array_keys(Artisan::all());

        foreach ($this->scheduledCommands() as $line) {
            $name = strtok($line, ' ');
            $this->assertContains($name, $registered, "scheduled command '{$name}' is not registered");
        }
    }

    public function test_schedule_contains_no_cache_flush_or_compiled_cache_rebuild(): void
    {
        $commands = $this->scheduledCommands();

        foreach (['maintenance:run --task=cache', 'route:cache', 'view:cache', 'config:cache', 'session:gc', 'cache:optimize'] as $forbidden) {
            $this->assertNotContains($forbidden, $commands, "schedule must not contain '{$forbidden}'");
        }
    }

    public function test_schedule_contains_no_argumentless_queue_monitor(): void
    {
        $this->assertNotContains('queue:monitor', $this->scheduledCommands());
    }

    public function test_approved_workloads_remain_scheduled(): void
    {
        $this->assertSame([
            'maintenance:run --task=metrics',
            'maintenance:run --task=database',
            'maintenance:run --task=logs',
            'backup:run --type=all',
            'backup:run --type=database',
            'queue:restart',
        ], $this->scheduledCommands());
    }

    public function test_scheduler_disabled_registers_nothing(): void
    {
        config(['app.enable_scheduler' => false]);
        $schedule = new Schedule();
        $kernel = $this->app->make(ConsoleKernel::class);
        $method = new ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $this->assertSame([], $schedule->events());
    }
}
```

- [ ] **Step 2: Run to verify RED**

Run: `./vendor/bin/phpunit tests/Feature/Console/ScheduleContractTest.php`
Expected: FAIL — `scheduled command 'session:gc' is not registered`; forbidden `maintenance:run --task=cache`; `queue:monitor` present; exact-list mismatch. `test_scheduler_disabled_registers_nothing` PASSES (existing gate). Save output to the Gate-3 evidence scratch file.

- [ ] **Step 3: Minimal implementation** — in `app/Console/Kernel.php` delete these blocks entirely (comment line + `$schedule->command(...)` chain): "Cache Maintenance" (`maintenance:run --task=cache`), "Queue Health Check" (`queue:monitor`), "Session Cleanup" (`session:gc`), "Cache Optimization" (`cache:optimize`), "Route Cache", "View Cache", "Config Cache". Leave every other block byte-for-byte unchanged. Resulting body after the gate:

```php
        // System Health Monitoring
        $schedule->command('maintenance:run --task=metrics')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Database Optimization
        $schedule->command('maintenance:run --task=database')
            ->weekly()
            ->sundays()
            ->at('03:00')
            ->withoutOverlapping();

        // Log Cleanup
        $schedule->command('maintenance:run --task=logs')
            ->dailyAt('04:00')
            ->withoutOverlapping();

        // System Backup
        $schedule->command('backup:run --type=all')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Database Backup
        $schedule->command('backup:run --type=database')
            ->everySixHours()
            ->withoutOverlapping()
            ->runInBackground();

        // Queue Restart
        $schedule->command('queue:restart')
            ->hourly()
            ->withoutOverlapping();

        // Compiled config/route/view caches are built by the deploy step
        // (.github/workflows/production.yml), never on a schedule. The
        // application cache store is never flushed on a schedule: it holds
        // rate-limit counters, OIDC state and these overlap locks (GAP-054).
```

- [ ] **Step 4: Run to verify GREEN**

Run: `./vendor/bin/phpunit tests/Feature/Console/ScheduleContractTest.php` → PASS (5 tests).
Run: `ENABLE_SCHEDULER=true php artisan schedule:list` → exactly the 6 entries above.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Kernel.php tests/Feature/Console/ScheduleContractTest.php
git commit -m "fix(GAP-054): schedule only real, cache-safe workloads"
```

---

### Task 2: Cache maintenance fails closed

**Files:**
- Test: `tests/Feature/Console/CacheMaintenanceFailClosedTest.php` (create)
- Modify: `app/Console/Commands/MaintenanceCommand.php:35-102`
- Modify: `tests/Feature/QualityAssuranceTest.php:222-235`, `tests/Feature/FinalSystemTest.php:816-829`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CacheMaintenanceFailClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_task_refuses_and_mutates_nothing(): void
    {
        Cache::put('gap054:probe', 'kept', 600);
        RateLimiter::hit('gap054-login-probe', 60);

        $exit = Artisan::call('maintenance:run', ['--task' => 'cache']);

        $this->assertNotSame(0, $exit);
        $this->assertSame('kept', Cache::get('gap054:probe'));
        $this->assertSame(1, RateLimiter::attempts('gap054-login-probe'));
        $this->assertStringContainsString('no maintenance-owned cache namespace', Artisan::output());
        $this->assertDatabaseMissing('maintenance_tasks', ['task' => 'Clear application cache']);
    }

    public function test_all_task_does_not_touch_the_cache(): void
    {
        Cache::put('gap054:probe', 'kept', 600);

        Artisan::call('maintenance:run', ['--task' => 'all']);

        $this->assertSame('kept', Cache::get('gap054:probe'));
        $this->assertDatabaseMissing('maintenance_tasks', ['task' => 'Clear application cache']);
    }
}
```

- [ ] **Step 2: Run to verify RED**

Run: `./vendor/bin/phpunit tests/Feature/Console/CacheMaintenanceFailClosedTest.php`
Expected: FAIL — exit code 0 and `Cache::get('gap054:probe')` is null (flushed).

- [ ] **Step 3: Minimal implementation** — in `MaintenanceCommand`:

Replace the `case 'cache':` branch in `handle()`:

```php
            case 'cache':
                $this->error('Refusing cache maintenance: there is no maintenance-owned cache namespace. '
                    . 'The application cache holds rate-limit counters, OIDC login state and scheduler locks, '
                    . 'so it is never flushed by maintenance (GAP-054).');
                return 1;
```

Remove `$this->clearCache();` from `runAllTasks()` and delete the now-unused private `clearCache()` method and the `use Illuminate\Support\Facades\Cache;` import if nothing else uses it (check with `grep -n "Cache::" app/Console/Commands/MaintenanceCommand.php`).

- [ ] **Step 4: Update callers that asserted the old behavior**

`tests/Feature/QualityAssuranceTest.php` `test_maintenance_commands()` and `tests/Feature/FinalSystemTest.php` `test_maintenance_task_execution()` — replace their bodies' command/assertions with:

```php
        $exitCode = Artisan::call('maintenance:run', ['--task' => 'logs']);
        $this->assertEquals(0, $exitCode);

        $this->assertDatabaseHas('maintenance_tasks', [
            'task' => 'Cleanup old logs',
        ]);

        $this->assertNotEquals(0, Artisan::call('maintenance:run', ['--task' => 'cache']));
```

(keep the existing `$this->actingAs($this->admin);` line and docblock).

- [ ] **Step 5: Run to verify GREEN**

Run: `./vendor/bin/phpunit tests/Feature/Console/CacheMaintenanceFailClosedTest.php`
Run: `./vendor/bin/phpunit --filter 'test_maintenance_commands|test_maintenance_task_execution' tests/Feature/QualityAssuranceTest.php tests/Feature/FinalSystemTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/MaintenanceCommand.php tests/Feature/Console/CacheMaintenanceFailClosedTest.php tests/Feature/QualityAssuranceTest.php tests/Feature/FinalSystemTest.php
git commit -m "fix(GAP-054): cache maintenance fails closed instead of flushing"
```

---

### Task 3: Credential-safe MySQL execution everywhere

Covers `BackupCommand::backupDatabase()`, `MaintenanceCommand::createBackup()`
(a third command-line-password site found while planning; covered by the
approved rule "no database password on the command line"), and
`DatabaseBackupService` full dump, incremental dump and restore.

**Files:**
- Create: `app/Services/Backup/MysqlClient.php`
- Test: `tests/Unit/Services/Backup/MysqlClientTest.php`, `tests/Architecture/NoCommandLineDatabasePasswordTest.php`
- Modify: `app/Console/Commands/BackupCommand.php:79-122`, `app/Console/Commands/MaintenanceCommand.php:263-305`, `app/Services/DatabaseBackupService.php:35-450`

**Interfaces:**
- Produces: `App\Services\Backup\MysqlClient` with
  - `public function dump(array $config, string $resultFile, array $options = [], array $tables = []): void` — `@param array<string, mixed> $config`, `@param list<string> $options`, `@param list<string> $tables`; throws `\RuntimeException` on non-zero exit.
  - `public function restore(array $config, string $inputFile): void` — throws `\RuntimeException`.

- [ ] **Step 1: Write the failing tests**

`tests/Architecture/NoCommandLineDatabasePasswordTest.php`:

```php
<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class NoCommandLineDatabasePasswordTest extends TestCase
{
    public function test_no_application_code_passes_a_password_option_to_mysql_clients(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app', \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/--password=|\s-p\{?\$|\s-p%s/', $source) === 1) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        $this->assertSame([], $offenders, 'MySQL passwords must never be passed as process arguments (GAP-054)');
    }
}
```

`tests/Unit/Services/Backup/MysqlClientTest.php`:

```php
<?php

namespace Tests\Unit\Services\Backup;

use App\Services\Backup\MysqlClient;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class MysqlClientTest extends TestCase
{
    private const PASSWORD = 'p@ss w0rd;$(rm -rf /)"\'`&';

    /** @return array<string, mixed> */
    private function config(): array
    {
        return ['host' => 'db.internal', 'port' => 3307, 'username' => 'zena', 'password' => self::PASSWORD, 'database' => 'zena_prod'];
    }

    /** @param array<int, string>|string $command */
    private function optionFileFrom(array|string $command): string
    {
        $this->assertIsArray($command, 'command must be an argument array, never a shell string');
        $this->assertStringStartsWith('--defaults-extra-file=', $command[1], '--defaults-extra-file must be the first option');

        return substr($command[1], strlen('--defaults-extra-file='));
    }

    public function test_dump_keeps_password_out_of_arguments_and_in_a_private_option_file(): void
    {
        $seen = [];
        Process::fake(function (PendingProcess $process) use (&$seen) {
            $file = $this->optionFileFrom($process->command);
            $seen = [
                'command' => $process->command,
                'mode' => fileperms($file) & 0777,
                'contents' => (string) file_get_contents($file),
                'file' => $file,
            ];

            return Process::result();
        });

        (new MysqlClient())->dump($this->config(), '/tmp/out.sql', ['--single-transaction'], ['users']);

        $this->assertSame('mysqldump', $seen['command'][0]);
        $this->assertNotContains(self::PASSWORD, $seen['command']);
        foreach ($seen['command'] as $arg) {
            $this->assertStringNotContainsString(self::PASSWORD, $arg);
            $this->assertStringNotContainsString('--password', $arg);
        }
        $this->assertContains('--host=db.internal', $seen['command']);
        $this->assertContains('--port=3307', $seen['command']);
        $this->assertContains('--result-file=/tmp/out.sql', $seen['command']);
        $this->assertSame(['zena_prod', 'users'], array_slice($seen['command'], -2));
        $this->assertSame(0600, $seen['mode']);
        $this->assertStringContainsString('user="zena"', $seen['contents']);
        $this->assertStringContainsString('password=', $seen['contents']);
        $this->assertFileDoesNotExist($seen['file']);
    }

    public function test_option_file_is_removed_when_dump_fails(): void
    {
        $file = null;
        Process::fake(function (PendingProcess $process) use (&$file) {
            $file = $this->optionFileFrom($process->command);

            return Process::result(errorOutput: 'boom', exitCode: 2);
        });

        try {
            (new MysqlClient())->dump($this->config(), '/tmp/out.sql');
            $this->fail('dump must throw on a non-zero exit');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString(self::PASSWORD, $e->getMessage());
        }

        $this->assertNotNull($file);
        $this->assertFileDoesNotExist($file);
    }

    public function test_restore_streams_the_file_without_shell_redirection(): void
    {
        $input = tempnam(sys_get_temp_dir(), 'gap054-restore-');
        file_put_contents($input, 'SELECT 1;');
        $seen = [];
        Process::fake(function (PendingProcess $process) use (&$seen) {
            $this->optionFileFrom($process->command);
            $seen = $process->command;

            return Process::result();
        });

        (new MysqlClient())->restore($this->config(), $input);
        unlink($input);

        $this->assertSame('mysql', $seen[0]);
        $this->assertSame('zena_prod', end($seen));
        $this->assertNotContains('<', $seen);
    }
}
```

- [ ] **Step 2: Run to verify RED**

Run: `./vendor/bin/phpunit tests/Architecture/NoCommandLineDatabasePasswordTest.php tests/Unit/Services/Backup/MysqlClientTest.php`
Expected: architecture test FAILS listing `app/Console/Commands/BackupCommand.php`, `app/Console/Commands/MaintenanceCommand.php`, `app/Services/DatabaseBackupService.php`; client test errors with `Class "App\Services\Backup\MysqlClient" not found`.

- [ ] **Step 3: Implement `MysqlClient`**

```php
<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Process;

/**
 * Runs the MySQL command-line clients without ever placing credentials in
 * process arguments (GAP-054): user and password go into a temporary option
 * file readable only by the current user, removed on every exit path.
 */
final class MysqlClient
{
    /**
     * @param array<string, mixed> $config
     * @param list<string> $options
     * @param list<string> $tables
     */
    public function dump(array $config, string $resultFile, array $options = [], array $tables = []): void
    {
        $this->withOptionFile($config, function (string $optionFile) use ($config, $resultFile, $options, $tables): void {
            $command = array_merge(
                ['mysqldump', '--defaults-extra-file=' . $optionFile],
                $this->connectionArguments($config),
                $options,
                ['--result-file=' . $resultFile, (string) $config['database']],
                $tables,
            );

            $result = Process::timeout(3600)->run($command);
            if (!$result->successful()) {
                throw new \RuntimeException('mysqldump failed with exit code ' . $result->exitCode());
            }
        });
    }

    /** @param array<string, mixed> $config */
    public function restore(array $config, string $inputFile): void
    {
        $this->withOptionFile($config, function (string $optionFile) use ($config, $inputFile): void {
            $input = fopen($inputFile, 'rb');
            if ($input === false) {
                throw new \RuntimeException('Cannot open restore input file');
            }

            try {
                $command = array_merge(
                    ['mysql', '--defaults-extra-file=' . $optionFile],
                    $this->connectionArguments($config),
                    [(string) $config['database']],
                );
                $result = Process::timeout(3600)->input($input)->run($command);
            } finally {
                fclose($input);
            }

            if (!$result->successful()) {
                throw new \RuntimeException('mysql restore failed with exit code ' . $result->exitCode());
            }
        });
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private function connectionArguments(array $config): array
    {
        return [
            '--host=' . (string) ($config['host'] ?? '127.0.0.1'),
            '--port=' . (string) ($config['port'] ?? 3306),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param callable(string): void $callback
     */
    private function withOptionFile(array $config, callable $callback): void
    {
        $path = tempnam(sys_get_temp_dir(), 'zena-mysql-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create MySQL option file');
        }

        try {
            chmod($path, 0600);
            $contents = "[client]\n"
                . 'user=' . $this->quote((string) ($config['username'] ?? '')) . "\n"
                . 'password=' . $this->quote((string) ($config['password'] ?? '')) . "\n";
            if (file_put_contents($path, $contents) === false) {
                throw new \RuntimeException('Cannot write MySQL option file');
            }

            $callback($path);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function quote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
```

- [ ] **Step 4: Route the three call sites through `MysqlClient`**

`BackupCommand::backupDatabase()` — replace the `sprintf(...)`/`exec(...)`/`$returnCode` block inside `if ($driver === 'mysql') { ... }` (keep the incomplete-config check and the empty-file check after it):

```php
            app(MysqlClient::class)->dump(
                $config,
                $filepath,
                ['--single-transaction', '--routines', '--triggers'],
            );
```

Add `use App\Services\Backup\MysqlClient;`.

`MaintenanceCommand::createBackup()` — same replacement of its `sprintf`/`exec` block (keep the checks), same options, plus the `use` import.

`DatabaseBackupService`:
- `createFullBackup()`: replace `$command = $this->buildMysqldumpCommand(...)` + `Process::run($command)` + its `successful()` check with `app(MysqlClient::class)->dump($config, $filepath, $this->fullDumpOptions($config));`
- `createIncrementalBackup()`: replace with `app(MysqlClient::class)->dump($config, $filepath, ['--single-transaction', '--where=updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)', '--no-create-info', '--complete-insert'], $this->getTablesWithUpdatedAt());` (database name now precedes the table list, which is mysqldump's required order).
- `restoreFromBackup()`: replace with `app(MysqlClient::class)->restore($config, $filepath);`
- Replace `buildMysqldumpCommand()` with:

```php
    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private function fullDumpOptions(array $config): array
    {
        $options = [
            '--single-transaction', '--routines', '--triggers', '--events',
            '--add-drop-database', '--add-drop-table', '--create-options',
            '--disable-keys', '--extended-insert', '--quick', '--lock-tables=false',
        ];
        foreach ($this->excludedTables as $table) {
            $options[] = '--ignore-table=' . $config['database'] . '.' . $table;
        }

        return $options;
    }
```

- Delete `buildIncrementalMysqldumpCommand()` and `buildMysqlRestoreCommand()`.
- `compressBackup()` / `decompressBackup()`: `Process::run(['gzip', $filepath]);` / `Process::run(['gunzip', $filepath]);`

- [ ] **Step 5: Run to verify GREEN**

Run: `./vendor/bin/phpunit tests/Architecture/NoCommandLineDatabasePasswordTest.php tests/Unit/Services/Backup/MysqlClientTest.php tests/Feature/BackupCommandTest.php`
Expected: PASS. `grep -rn -- '--password' app/` → no output.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Backup/MysqlClient.php app/Console/Commands/BackupCommand.php app/Console/Commands/MaintenanceCommand.php app/Services/DatabaseBackupService.php tests/Unit/Services/Backup/MysqlClientTest.php tests/Architecture/NoCommandLineDatabasePasswordTest.php
git commit -m "fix(GAP-054): keep MySQL credentials out of process arguments"
```

---

### Task 4: Typed archives, per-type retention, configured storage

**Files:**
- Create: `app/Services/Backup/BackupArchiveStore.php`
- Modify: `config/backup.php`, `app/Console/Commands/BackupCommand.php:24-77,203-214,296-363`, `app/Services/LaunchChecklistService.php:405-425`
- Test: `tests/Feature/Console/BackupRetentionTest.php`, `tests/Feature/Console/BackupStorageLocationTest.php` (create); modify `tests/Feature/BackupCommandTest.php`, `tests/Unit/LaunchChecklistServiceBackupTest.php`

**Interfaces:**
- Produces `App\Services\Backup\BackupArchiveStore`:
  - `public static function fromConfig(): self`
  - `public function stagingRoot(): string` — always `storage_path('backups')`
  - `public function store(string $localArchive): string` — returns final location (local path or `disk:path`); throws `\RuntimeException` on write failure
  - `public function prune(string $type): int` — per-type retention from config; returns deleted count
  - `public function newestTimestamp(): ?int`
  - `public function localPathsExcludedFromFileBackups(): list<string>` — used in Task 5
  - `public const TYPED_ARCHIVE_PATTERN = '/^backup_(full|database|files|config)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz$/'`

- [ ] **Step 1: Config**

`config/backup.php` becomes:

```php
<?php

return [
    // Legacy keys kept for callers outside backup:run (unchanged meaning).
    'max_backups' => (int) env('BACKUP_MAX_BACKUPS', 10),
    'max_age_days' => (int) env('BACKUP_MAX_AGE_DAYS', 30),

    // null = keep archives in storage/backups exactly as before GAP-054.
    // Set to a Laravel filesystem disk name to store finished archives there.
    'disk' => env('BACKUP_DISK'),

    // Directory on BACKUP_DISK (ignored when disk is null).
    'path' => env('BACKUP_PATH', 'backups'),

    // Per-type retention (GAP-054 Gate 2, Q3). files/config fall back to full.
    'retention' => [
        'full' => [
            'max_backups' => (int) env('BACKUP_FULL_MAX_BACKUPS', 30),
            'max_age_days' => (int) env('BACKUP_FULL_MAX_AGE_DAYS', 30),
        ],
        'database' => [
            'max_backups' => (int) env('BACKUP_DATABASE_MAX_BACKUPS', 28),
            'max_age_days' => (int) env('BACKUP_DATABASE_MAX_AGE_DAYS', 7),
        ],
    ],
];
```

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Console/BackupRetentionTest.php`:

```php
<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupRetentionTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['backup.disk' => null]);
        $this->dir = storage_path('backups');
        @mkdir($this->dir, 0755, true);
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        parent::tearDown();
    }

    private function clean(): void
    {
        foreach (glob($this->dir . '/backup_*') ?: [] as $p) {
            is_dir($p) ? exec('rm -rf ' . escapeshellarg($p)) : unlink($p);
        }
    }

    private function archive(string $type, int $daysOld, int $seq = 0): string
    {
        $ts = time() - ($daysOld * 86400) - $seq;
        $path = sprintf('%s/backup_%s_%s.tar.gz', $this->dir, $type, date('Y-m-d_H-i-s', $ts));
        touch($path, $ts);

        return $path;
    }

    public function test_database_retention_is_independent_of_full_archives(): void
    {
        $fullOld = $this->archive('full', 29);
        $fullExpired = $this->archive('full', 31);
        $dbExpired = $this->archive('database', 8);
        $dbBeyondCount = $this->archive('database', 6);
        $legacy = $this->dir . '/backup_2020-01-01_01-00-00.tar.gz';
        touch($legacy, time() - 400 * 86400);

        for ($i = 1; $i <= 40; $i++) {           // 40 fresh database archives
            $this->archive('database', 0, $i * 60);
        }

        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        // A database run prunes only database archives.
        $this->assertFileExists($fullOld);
        $this->assertFileExists($fullExpired, 'a database run must not prune full archives');
        $this->assertFileDoesNotExist($dbExpired, 'older than 7 days');
        $this->assertFileDoesNotExist($dbBeyondCount, 'within 7 days but outside the newest 28');
        $this->assertCount(28, glob($this->dir . '/backup_database_*.tar.gz') ?: []);
        $this->assertFileExists($legacy, 'legacy untyped archives are never deleted');
    }

    public function test_full_retention_keeps_30_days_of_full_archives(): void
    {
        $fullKept = $this->archive('full', 29);
        $fullExpired = $this->archive('full', 31);
        $dbFresh = $this->archive('database', 1);

        $this->artisan('backup:run', ['--type' => 'all'])->assertExitCode(0);

        $this->assertFileExists($fullKept);
        $this->assertFileDoesNotExist($fullExpired);
        $this->assertFileExists($dbFresh, 'a full run must not prune database archives');
    }

    public function test_ten_archive_legacy_cap_no_longer_evicts_full_backups(): void
    {
        $full = $this->archive('full', 1);
        for ($i = 1; $i <= 12; $i++) {           // > the old shared cap of 10
            $this->archive('database', 0, $i * 60);
        }

        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        $this->assertFileExists($full);
    }

    public function test_a_full_run_is_named_full(): void
    {
        $this->artisan('backup:run', ['--type' => 'all'])->assertExitCode(0);

        $this->assertCount(1, glob($this->dir . '/backup_full_*.tar.gz') ?: []);
    }
}
```

`tests/Feature/Console/BackupStorageLocationTest.php`:

```php
<?php

namespace Tests\Feature\Console;

use App\Services\LaunchChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class BackupStorageLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (glob(storage_path('backups') . '/backup_*') ?: [] as $p) {
            is_dir($p) ? exec('rm -rf ' . escapeshellarg($p)) : unlink($p);
        }
        parent::tearDown();
    }

    private function checklistSeesBackup(): bool
    {
        $m = new ReflectionMethod(LaunchChecklistService::class, 'checkBackupSystem');
        $m->setAccessible(true);

        return (bool) $m->invoke(new LaunchChecklistService());
    }

    public function test_unset_disk_keeps_the_legacy_location(): void
    {
        config(['backup.disk' => null]);

        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        $this->assertCount(1, glob(storage_path('backups') . '/backup_database_*.tar.gz') ?: []);
        $this->assertTrue($this->checklistSeesBackup());
    }

    public function test_configured_disk_and_path_receive_the_archive(): void
    {
        Storage::fake('gap054-offsite');
        config(['backup.disk' => 'gap054-offsite', 'backup.path' => 'zena/backups']);

        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        $files = Storage::disk('gap054-offsite')->files('zena/backups');
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/backup_database_.*\.tar\.gz$/', $files[0]);
        $this->assertSame([], glob(storage_path('backups') . '/backup_database_*.tar.gz') ?: [], 'no local copy left behind');
        $this->assertTrue($this->checklistSeesBackup());
    }

    public function test_unwritable_disk_fails_and_deletes_nothing(): void
    {
        $root = storage_path('framework/testing/gap054-readonly');
        @mkdir($root . '/zena/backups', 0755, true);
        $old = $root . '/zena/backups/backup_database_2000-01-01_00-00-00.tar.gz';
        file_put_contents($old, 'old');
        touch($old, time() - 400 * 86400);   // expired: would be pruned if prune ran
        chmod($root . '/zena/backups', 0555);
        config([
            'filesystems.disks.gap054-readonly' => ['driver' => 'local', 'root' => $root, 'throw' => false],
            'backup.disk' => 'gap054-readonly',
            'backup.path' => 'zena/backups',
        ]);

        try {
            $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(1);
            $this->assertFileExists($old, 'a failed store must not prune anything');
        } finally {
            chmod($root . '/zena/backups', 0755);
            exec('rm -rf ' . escapeshellarg($root));
        }
    }
}
```

(Requires a non-root test user so the `0555` directory is really unwritable — true for local runs and GitHub-hosted runners.)

Update `tests/Feature/BackupCommandTest.php` `test_old_archives_beyond_max_backups_are_cleaned_up()`: replace `config(['backup.max_backups' => 1]);` with `config(['backup.disk' => null, 'backup.retention.database.max_backups' => 1]);` and change both globs to `/backup_database_*.tar.gz`. In `test_database_only_backup_produces_a_single_compressed_archive()` change the archive glob to `/backup_database_*.tar.gz`.

`tests/Unit/LaunchChecklistServiceBackupTest.php`: add `config(['backup.disk' => null]);` as the first line after `parent::setUp();` (existing expectations stay valid — legacy patterns still match).

- [ ] **Step 3: Run to verify RED**

Run: `./vendor/bin/phpunit tests/Feature/Console/BackupRetentionTest.php tests/Feature/Console/BackupStorageLocationTest.php tests/Feature/BackupCommandTest.php`
Expected: FAIL — no `backup_full_*`/`backup_database_*` names; configured disk empty; 10-archive cap deletes the kept full archive.

- [ ] **Step 4: Implement `BackupArchiveStore`**

```php
<?php

namespace App\Services\Backup;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Where finished backup archives live and how long they are kept (GAP-054).
 * disk === null keeps the pre-GAP-054 storage/backups directory.
 */
final class BackupArchiveStore
{
    public const TYPED_ARCHIVE_PATTERN = '/^backup_(full|database|files|config)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz$/';

    public function __construct(private readonly ?string $disk, private readonly string $path)
    {
    }

    public static function fromConfig(): self
    {
        $disk = config('backup.disk');

        return new self(is_string($disk) && $disk !== '' ? $disk : null, trim((string) config('backup.path', 'backups'), '/'));
    }

    public function stagingRoot(): string
    {
        return storage_path('backups');
    }

    public function store(string $localArchive): string
    {
        if ($this->disk === null) {
            return $localArchive;
        }

        $target = $this->path . '/' . basename($localArchive);
        $stream = fopen($localArchive, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Cannot read backup archive for upload');
        }

        try {
            $written = $this->filesystem()->writeStream($target, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($written !== true) {
            throw new \RuntimeException("Backup disk '{$this->disk}' rejected the archive; nothing was pruned");
        }

        unlink($localArchive);

        return $this->disk . ':' . $target;
    }

    public function prune(string $type): int
    {
        $limits = config("backup.retention.{$type}") ?? config('backup.retention.full');
        $maxBackups = (int) ($limits['max_backups'] ?? 30);
        $cutoff = time() - ((int) ($limits['max_age_days'] ?? 30) * 86400);

        $archives = array_values(array_filter(
            $this->typedArchives(),
            static fn (array $a): bool => $a['type'] === $type,
        ));
        usort($archives, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        $deleted = 0;
        foreach ($archives as $index => $archive) {
            if ($index >= $maxBackups || $archive['mtime'] < $cutoff) {
                $this->delete($archive['name']);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function newestTimestamp(): ?int
    {
        $newest = null;

        if ($this->disk === null) {
            foreach (['/backup_*.tar.gz', '/backup_*', '/*.sql', '/*.sql.gz'] as $pattern) {
                foreach (glob($this->stagingRoot() . $pattern) ?: [] as $path) {
                    $mtime = filemtime($path);
                    if ($mtime !== false && ($newest === null || $mtime > $newest)) {
                        $newest = $mtime;
                    }
                }
            }

            return $newest;
        }

        foreach ($this->filesystem()->files($this->path) as $file) {
            if (str_starts_with(basename($file), 'backup_')) {
                $mtime = $this->filesystem()->lastModified($file);
                $newest = $newest === null ? $mtime : max($newest, $mtime);
            }
        }

        return $newest;
    }

    /** @return list<string> absolute local directories a files backup must never copy */
    public function localPathsExcludedFromFileBackups(): array
    {
        $paths = [$this->stagingRoot(), storage_path('app/' . trim((string) config('database.backup.path', 'backups/database'), '/'))];

        if ($this->disk !== null && config("filesystems.disks.{$this->disk}.driver") === 'local') {
            $root = (string) config("filesystems.disks.{$this->disk}.root");
            $paths[] = rtrim($root, '/') . '/' . $this->path;
        }

        return $paths;
    }

    /** @return list<array{name: string, type: string, mtime: int}> */
    private function typedArchives(): array
    {
        $result = [];

        if ($this->disk === null) {
            foreach (glob($this->stagingRoot() . '/backup_*.tar.gz') ?: [] as $path) {
                $name = basename($path);
                if (preg_match(self::TYPED_ARCHIVE_PATTERN, $name, $m) === 1) {
                    $result[] = ['name' => $name, 'type' => $m[1], 'mtime' => (int) filemtime($path)];
                }
            }

            return $result;
        }

        foreach ($this->filesystem()->files($this->path) as $file) {
            $name = basename($file);
            if (preg_match(self::TYPED_ARCHIVE_PATTERN, $name, $m) === 1) {
                $result[] = ['name' => $name, 'type' => $m[1], 'mtime' => $this->filesystem()->lastModified($file)];
            }
        }

        return $result;
    }

    private function delete(string $name): void
    {
        if ($this->disk === null) {
            @unlink($this->stagingRoot() . '/' . $name);

            return;
        }

        $this->filesystem()->delete($this->path . '/' . $name);
    }

    private function filesystem(): Filesystem
    {
        return Storage::disk((string) $this->disk);
    }
}
```

- [ ] **Step 5: Wire `BackupCommand`**

Replace `handle()` with:

```php
    public function handle()
    {
        $type = (string) $this->option('type');

        $this->info('Starting backup process...');

        $task = MaintenanceTask::create([
            'task' => 'System backup',
            'level' => 'info',
            'priority' => 'high',
            'status' => 'running',
            'started_at' => now()
        ]);

        if (!in_array($type, ['all', 'database', 'files', 'config'], true)) {
            $this->error('Invalid backup type. Available types: all, database, files, config');
            $task->markAsFailed('Invalid backup type');
            return 1;
        }

        $archiveType = $type === 'all' ? 'full' : $type;

        try {
            $backupDir = $this->createBackupDirectory($archiveType);

            if ($type === 'all' || $type === 'database') {
                $this->backupDatabase($backupDir);
            }
            if ($type === 'all' || $type === 'files') {
                $this->backupFiles($backupDir);
            }
            if ($type === 'all' || $type === 'config') {
                $this->backupConfig($backupDir);
            }

            $this->createBackupManifest($backupDir);
            $archive = $this->compressBackup($backupDir);
            $store = BackupArchiveStore::fromConfig();
            $location = $store->store($archive);
            $pruned = $store->prune($archiveType);
            $this->info("✓ Backup stored: {$location}" . ($pruned > 0 ? " ({$pruned} old {$archiveType} backups removed)" : ''));

            $task->markAsCompleted(['backup_type' => $type]);
            $this->info('Backup completed successfully!');
            return 0;
        } catch (\Exception $e) {
            $task->markAsFailed($e->getMessage());
            $this->error('Backup failed: ' . $e->getMessage());
            return 1;
        }
    }
```

(On a store failure the local archive stays in `storage/backups` for the operator; no prune runs.)

`createBackupDirectory(string $type): string` builds `storage_path('backups') . '/backup_' . $type . '_' . date('Y-m-d_H-i-s')`.
`compressBackup(string $backupDir): string` runs `Process::run(['tar', '-czf', $archivePath, '-C', dirname($backupDir), basename($backupDir)])`, throws on failure, removes the directory, returns `$archivePath`.
Delete `cleanupOldBackups()`. Add `use App\Services\Backup\BackupArchiveStore;` and `use Illuminate\Support\Facades\Process;`.

- [ ] **Step 6: Wire `LaunchChecklistService`**

Replace the body of `getLatestBackupTimestamp()` with `return BackupArchiveStore::fromConfig()->newestTimestamp();` and add the `use` import. Keep the explanatory comment above `checkBackupSystem()`.

- [ ] **Step 7: Run to verify GREEN**

Run: `./vendor/bin/phpunit tests/Feature/Console/BackupRetentionTest.php tests/Feature/Console/BackupStorageLocationTest.php tests/Feature/BackupCommandTest.php tests/Unit/LaunchChecklistServiceBackupTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Backup/BackupArchiveStore.php config/backup.php app/Console/Commands/BackupCommand.php app/Services/LaunchChecklistService.php tests/Feature/Console/BackupRetentionTest.php tests/Feature/Console/BackupStorageLocationTest.php tests/Feature/BackupCommandTest.php tests/Unit/LaunchChecklistServiceBackupTest.php
git commit -m "fix(GAP-054): typed backup archives, per-type retention, configured storage"
```

---

### Task 5: Backups never contain secrets or earlier backups

**Files:**
- Test: `tests/Feature/Console/BackupContentsSafetyTest.php` (create)
- Modify: `app/Console/Commands/BackupCommand.php` (`backupFiles()`, `backupConfig()`, `backupDirectory()`)

**Interfaces:** Consumes `BackupArchiveStore::localPathsExcludedFromFileBackups(): list<string>` from Task 4.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupContentsSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const SENTINEL = 'GAP054_SECRET_SENTINEL_5f1c';

    /** @var list<string> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['backup.disk' => null]);
        $this->clean();
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            @unlink($path);
        }
        $this->clean();
        exec('rm -rf ' . escapeshellarg(storage_path('app/backups/gap054-probe')));
        parent::tearDown();
    }

    private function clean(): void
    {
        foreach (glob(storage_path('backups') . '/backup_*') ?: [] as $p) {
            is_dir($p) ? exec('rm -rf ' . escapeshellarg($p)) : unlink($p);
        }
    }

    /** @return list<string> */
    private function runAndList(string $type): array
    {
        $this->artisan('backup:run', ['--type' => $type])->assertExitCode(0);
        $archives = glob(storage_path('backups') . '/backup_*.tar.gz') ?: [];
        $this->assertCount(1, $archives);
        exec('tar -tzf ' . escapeshellarg($archives[0]), $entries);

        return $entries;
    }

    private function writeSentinel(string $path): void
    {
        if (!file_exists($path)) {
            file_put_contents($path, 'APP_KEY=' . self::SENTINEL . "\n");
            $this->created[] = $path;
        }
    }

    public function test_full_backup_contains_no_env_files_or_secret_values(): void
    {
        $this->writeSentinel(base_path('.env'));
        $this->writeSentinel(base_path('.env.gap054probe'));

        $entries = $this->runAndList('all');

        foreach ($entries as $entry) {
            $this->assertDoesNotMatchRegularExpression('#(^|/)\.env(\.|$)#', $entry, "archive must not contain {$entry}");
        }
        $archive = (glob(storage_path('backups') . '/backup_*.tar.gz') ?: [])[0];
        exec('tar -xzOf ' . escapeshellarg($archive) . ' | grep -c ' . escapeshellarg(self::SENTINEL), $count);
        $this->assertSame('0', trim($count[0] ?? '0'));
    }

    public function test_files_backup_on_the_local_disk_never_contains_earlier_backups(): void
    {
        // The real nesting risk: BACKUP_DISK=local stores archives under
        // storage/app (the local disk root), which backupFiles() copies.
        $dir = storage_path('app/backups/gap054-probe');
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/backup_full_2026-01-01_00-00-00.tar.gz', 'old');
        config(['backup.disk' => 'local', 'backup.path' => 'backups/gap054-probe']);

        $this->artisan('backup:run', ['--type' => 'files'])->assertExitCode(0);

        $archives = glob($dir . '/backup_files_*.tar.gz') ?: [];
        $this->assertCount(1, $archives);
        exec('tar -tzf ' . escapeshellarg($archives[0]), $entries);
        $this->assertNotEmpty($entries);
        foreach ($entries as $entry) {
            $this->assertStringNotContainsString('gap054-probe', $entry, "archive must not contain {$entry}");
        }
    }
}
```

- [ ] **Step 2: Run to verify RED**

Run: `./vendor/bin/phpunit tests/Feature/Console/BackupContentsSafetyTest.php`
Expected: FAIL — entry `…/config/.env` present; entry `…/files/storage_app/backups/gap054-probe/…` present.

- [ ] **Step 3: Implement**

`backupConfig()`: delete the `// Backup environment file` block (the `if (file_exists(base_path('.env'))) { copy(...); }` lines). Add above `$configFiles`:

```php
        // Never collect .env / .env.*: secrets are kept out-of-band
        // (docs/runbooks/gap-049-host-provisioning.md) — GAP-054.
```

`backupFiles()`: compute `$excluded = BackupArchiveStore::fromConfig()->localPathsExcludedFromFileBackups();` and pass it as a third argument to each `backupDirectory(...)` call.

`backupDirectory(string $source, string $destination, array $excluded = []): void` (`@param list<string> $excluded`): normalise `$excluded` with `realpath()` (drop falses), and inside the loop skip any item whose `realpath($item->getPathname())` equals or starts with an excluded path + `/`; for skipped directories also skip their children (check the prefix on every item, which covers descendants). Also skip any item whose basename matches `/^\.env(\..*)?$/`.

- [ ] **Step 4: Run to verify GREEN**

Run: `./vendor/bin/phpunit tests/Feature/Console/BackupContentsSafetyTest.php tests/Feature/BackupCommandTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/BackupCommand.php tests/Feature/Console/BackupContentsSafetyTest.php
git commit -m "fix(GAP-054): backups never contain .env files or earlier backups"
```

---

### Task 6: Scheduler enablement documentation

**Files:**
- Modify: `docs/runbooks/gap-049-host-provisioning.md` (append section), `.env.example:34-36`

- [ ] **Step 1: `.env.example`** — replace lines 34-36 with:

```
# Scheduler: off by default. Before setting true in production read
# docs/runbooks/gap-049-host-provisioning.md → "Enable the scheduler (optional)".
ENABLE_SCHEDULER=false
```

- [ ] **Step 2: Runbook** — append:

```markdown
## Enable the scheduler (optional, GAP-054)

The application scheduler is off by default (`ENABLE_SCHEDULER=false`). After
the GAP-054 release its workloads are safe to enable. To enable on this host:

1. Run it on exactly **one** host. Multi-host scheduling is not supported yet.
2. Use Redis for the cache (`CACHE_DRIVER=redis`) so overlap locks are shared.
3. Set `ENABLE_SCHEDULER=true` in `shared/.env` and add the cron entry
   `* * * * * cd /var/www/zena/current && php artisan schedule:run >> /dev/null 2>&1`
   for the web-server user.
4. Recommended: set `BACKUP_DISK` (and `BACKUP_PATH`) to a disk on another
   machine/provider. Unset keeps archives in `storage/backups` on this host.
5. Retention defaults: full backups 30 days, database backups 7 days
   (`BACKUP_FULL_*` / `BACKUP_DATABASE_*` in `config/backup.php`).
6. Backups never contain `.env`. Keep a secure copy of `shared/.env`
   separately; a restore needs it.
7. Before enabling, review any pre-existing `storage/backups/backup_*`
   archives from before GAP-054: they may contain a copy of `.env` and are
   never removed automatically.
```

- [ ] **Step 3: Commit**

```bash
git add .env.example docs/runbooks/gap-049-host-provisioning.md
git commit -m "docs(GAP-054): document safe scheduler enablement"
```

---

### Task 7: Full verification (no commit unless fixes are needed)

- [ ] **Step 1:** `./vendor/bin/phpunit tests/Feature/Console tests/Unit/Services/Backup tests/Architecture/NoCommandLineDatabasePasswordTest.php tests/Feature/BackupCommandTest.php tests/Unit/LaunchChecklistServiceBackupTest.php` → PASS.
- [ ] **Step 2:** `./vendor/bin/phpunit --filter 'test_maintenance|test_backup' tests/Feature/QualityAssuranceTest.php tests/Feature/FinalSystemTest.php` → PASS.
- [ ] **Step 3:** `ENABLE_SCHEDULER=true php artisan schedule:list` → the 6 approved entries; `php artisan -n maintenance:run --task=cache; echo $?` → non-zero.
- [ ] **Step 4:** `grep -rn -- '--password' app/` → nothing; `grep -rn "base_path('.env')" app/Console/Commands/BackupCommand.php` → nothing; `grep -n "Cache::flush" app/Console/Commands/MaintenanceCommand.php` → nothing.
- [ ] **Step 5:** Remove the Task-0 scaffolding: `rm -rf vendor .env.testing-local-only`; `git status --porcelain` must list no untracked files.
- [ ] **Step 6:** Push; read CI with `gh pr checks 318` (PHPStan runs only in CI — fix any finding in a separate `fix(GAP-054): …` commit). Prepare `docs/owner-decisions/GAP-054/03-release.md` for Gate 3 with the RED/GREEN outputs.
