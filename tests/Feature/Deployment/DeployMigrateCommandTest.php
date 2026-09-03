<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Services\Deployment\MigrationClassificationService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeployMigrateCommandTest extends TestCase
{
    private string $fixtureDir;
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = storage_path('framework/testing/gap049-migrations-' . uniqid());
        File::makeDirectory($this->fixtureDir, 0755, true);
        $this->manifestPath = $this->fixtureDir . '/classifications.json';
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureDir);
        parent::tearDown();
    }

    private function writeMigrationFixture(string $name): void
    {
        File::put($this->fixtureDir . "/{$name}.php", "<?php\nreturn new class extends \\Illuminate\\Database\\Migrations\\Migration {\n public function up(): void {}\n public function down(): void {}\n};\n");
    }

    public function test_service_reports_unclassified_migration(): void
    {
        $this->writeMigrationFixture('2026_09_03_000001_add_expand_column');
        File::put($this->manifestPath, json_encode([]));

        $service = new MigrationClassificationService($this->manifestPath, $this->fixtureDir);
        $files = ['2026_09_03_000001_add_expand_column'];

        $this->assertTrue($service->hasUnclassified($files));
        $this->assertNull($service->classificationsFor($files)['2026_09_03_000001_add_expand_column']);
    }

    public function test_service_reports_expand_classification(): void
    {
        $this->writeMigrationFixture('2026_09_03_000002_add_expand_column');
        File::put($this->manifestPath, json_encode(['2026_09_03_000002_add_expand_column' => 'expand']));

        $service = new MigrationClassificationService($this->manifestPath, $this->fixtureDir);
        $files = ['2026_09_03_000002_add_expand_column'];

        $this->assertFalse($service->hasUnclassified($files));
        $this->assertFalse($service->hasBreaking($files));
    }

    public function test_service_reports_breaking_classification(): void
    {
        $this->writeMigrationFixture('2026_09_03_000003_drop_legacy_column');
        File::put($this->manifestPath, json_encode(['2026_09_03_000003_drop_legacy_column' => 'breaking']));

        $service = new MigrationClassificationService($this->manifestPath, $this->fixtureDir);
        $files = ['2026_09_03_000003_drop_legacy_column'];

        $this->assertTrue($service->hasBreaking($files));
    }

    public function test_command_fails_closed_on_unclassified_migration(): void
    {
        $this->writeMigrationFixture('2026_09_03_000004_unclassified');
        File::put($this->manifestPath, json_encode([]));

        $exitCode = $this->artisan('deploy:migrate', [
            '--manifest' => $this->manifestPath,
            '--migrations-path' => $this->fixtureDir,
        ])->run();

        $this->assertSame(1, $exitCode);
    }

    public function test_command_accepts_expand_only_migrations(): void
    {
        $this->writeMigrationFixture('2026_09_03_000005_expand_only');
        File::put($this->manifestPath, json_encode(['2026_09_03_000005_expand_only' => 'expand']));

        $exitCode = $this->artisan('deploy:migrate', [
            '--manifest' => $this->manifestPath,
            '--migrations-path' => $this->fixtureDir,
        ])->run();

        $this->assertSame(0, $exitCode);
    }

    public function test_command_rejects_breaking_migration_without_allow_breaking_flag(): void
    {
        $this->writeMigrationFixture('2026_09_03_000006_breaking');
        File::put($this->manifestPath, json_encode(['2026_09_03_000006_breaking' => 'breaking']));

        $exitCode = $this->artisan('deploy:migrate', [
            '--manifest' => $this->manifestPath,
            '--migrations-path' => $this->fixtureDir,
        ])->run();

        $this->assertSame(2, $exitCode);
    }

    public function test_command_rejects_breaking_migration_with_flag_but_no_maintenance_mode(): void
    {
        $this->writeMigrationFixture('2026_09_03_000007_breaking_flagged');
        File::put($this->manifestPath, json_encode(['2026_09_03_000007_breaking_flagged' => 'breaking']));

        $this->assertFalse(app()->isDownForMaintenance());

        $exitCode = $this->artisan('deploy:migrate', [
            '--manifest' => $this->manifestPath,
            '--migrations-path' => $this->fixtureDir,
            '--allow-breaking' => true,
        ])->run();

        $this->assertSame(3, $exitCode);
    }

    public function test_command_never_invokes_migrate_rollback(): void
    {
        $source = File::get(app_path('Console/Commands/DeployMigrateCommand.php'));
        $this->assertStringNotContainsString('migrate:rollback', $source);
    }
}
