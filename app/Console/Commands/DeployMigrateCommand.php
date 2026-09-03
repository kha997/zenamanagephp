<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Deployment\MigrationClassificationService;
use Illuminate\Console\Command;

/**
 * GAP-049: enforces the expand-vs-breaking migration classification contract
 * before any migration runs in a production deployment. This command never
 * invokes any automated rollback of the schema. See
 * docs/runbooks/gap-049-migration-safety.md.
 */
class DeployMigrateCommand extends Command
{
    protected $signature = 'deploy:migrate
        {--allow-breaking : Permit classified breaking migrations to run (requires maintenance mode already active)}
        {--manifest= : Override path to classifications.json (testing only)}
        {--migrations-path= : Override migrations directory (testing only)}';

    protected $description = 'Run pending migrations only after verifying every migration is classified (GAP-049 migration safety contract)';

    public function handle(): int
    {
        $manifestPath = $this->option('manifest') ?: database_path('migrations/classifications.json');
        $migrationsPath = $this->option('migrations-path') ?: database_path('migrations');

        $service = new MigrationClassificationService($manifestPath, $migrationsPath);
        $pending = $service->pendingMigrationFiles();

        if ($pending === []) {
            $this->info('No pending migrations.');
            return 0;
        }

        if ($service->hasUnclassified($pending)) {
            $unclassified = array_keys(array_filter(
                $service->classificationsFor($pending),
                static fn ($v) => $v === null
            ));
            $this->error('Unclassified pending migration(s), refusing to run: ' . implode(', ', $unclassified));
            $this->error('Add an entry to ' . $manifestPath . ' ("expand" or "breaking") before deploying.');
            return 1;
        }

        $hasBreaking = $service->hasBreaking($pending);

        if ($hasBreaking && !$this->option('allow-breaking')) {
            $this->error('Breaking migration(s) present in this deploy — refusing to run as an ordinary pre-cutover migration.');
            $this->error('Follow docs/runbooks/gap-049-migration-safety.md: classify, backup, enter maintenance mode, then re-run with --allow-breaking.');
            return 2;
        }

        if ($hasBreaking && $this->option('allow-breaking') && !$this->laravel->isDownForMaintenance()) {
            $this->error('Breaking migration(s) require the application to be in maintenance mode before running. Run `php artisan down` first.');
            return 3;
        }

        $this->call('migrate', ['--force' => true, '--isolated' => true]);

        return 0;
    }
}
