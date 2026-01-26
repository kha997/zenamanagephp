<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DataRetentionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataRetentionTest extends TestCase
{
    use DatabaseTransactions;

    private static bool $migrationsLoaded = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$migrationsLoaded) {
            Artisan::call('migrate', ['--force' => true]);
            self::$migrationsLoaded = true;
        }
    }

    public function test_tenant_tables_soft_delete_when_running_for_single_tenant(): void
    {
        $context = $this->prepareTenantContext('solo');
        $timestamp = Carbon::now()->subYears(3);

        $this->insertTenantRetentionRecords($context, $timestamp);

        $exitCode = Artisan::call('data:retention', [
            '--tenant' => $context['tenant']->id,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $results = DataRetentionService::executeRetentionPolicies(
            $context['tenant']->id,
            true,
            false,
            $context['tenant']
        );

        foreach (['audit_logs', 'project_activities', 'notifications'] as $table) {
            $this->assertArrayHasKey($table, $results);
            $this->assertArrayNotHasKey('skipped', $results[$table]);
            $this->assertGreaterThanOrEqual(1, $results[$table]['records_affected']);
        }
    }

    public function test_all_tenants_iteration_processes_notifications_for_each_tenant(): void
    {
        $first = $this->prepareTenantContext('alpha');
        $second = $this->prepareTenantContext('beta');
        $timestamp = Carbon::now()->subDays(200);

        $this->insertTenantRetentionRecords($first, $timestamp);
        $this->insertTenantRetentionRecords($second, $timestamp);

        $exitCode = Artisan::call('data:retention', [
            '--all-tenants' => true,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);

        foreach ([$first, $second] as $chunk) {
            $results = DataRetentionService::executeRetentionPolicies(
                $chunk['tenant']->id,
                true,
                false,
                $chunk['tenant']
            );

            $this->assertArrayHasKey('notifications', $results);
            $this->assertGreaterThanOrEqual(1, $results['notifications']['records_affected']);
        }
    }

    public function test_system_mode_dry_run_preserves_query_logs(): void
    {
        $timestamp = Carbon::now()->subDays(40);

        $logId = DB::table('query_logs')->insertGetId([
            'query_hash' => Str::random(16),
            'sql' => 'SELECT 1',
            'bindings' => json_encode([]),
            'execution_time' => 12.3,
            'connection' => 'mysql',
            'user_id' => Str::ulid(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'url' => '/test',
            'method' => 'GET',
            'memory_usage' => 1024,
            'rows_affected' => 1,
            'rows_returned' => 1,
            'query_type' => 'SELECT',
            'is_slow' => false,
            'is_error' => false,
            'error_message' => null,
            'executed_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $exitCode = Artisan::call('data:retention', [
            '--system' => true,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('query_logs', ['id' => $logId]);
    }

    /**
     * @return array{tenant: Tenant, project: Project, user: User}
     */
    private function prepareTenantContext(string $suffix): array
    {
        $tenant = Tenant::factory()->create(['name' => "Tenant {$suffix}"]);

        $project = Project::create([
            'tenant_id' => $tenant->id,
            'code' => strtoupper("PJ{$suffix}"),
            'name' => "Project {$suffix}",
            'status' => 'active',
            'description' => 'Retention seed',
            'budget_total' => 1000,
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => "User {$suffix}",
            'email' => Str::lower("user.{$suffix}@example.com"),
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);

        return compact('tenant', 'project', 'user');
    }

    /**
     * @param array{tenant: Tenant, project: Project, user: User} $context
     */
    private function insertTenantRetentionRecords(array $context, Carbon $createdAt): void
    {
        $tenantId = $context['tenant']->id;
        $userId = $context['user']->id;
        $projectId = $context['project']->id;

        DB::table('audit_logs')->insert([
            'user_id' => $userId,
            'action' => 'cleanup',
            'entity_type' => 'Project',
            'project_id' => $projectId,
            'tenant_id' => $tenantId,
            'ip_address' => '127.0.0.1',
            'old_data' => json_encode([]),
            'new_data' => json_encode([]),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        DB::table('project_activities')->insert([
            'project_id' => $projectId,
            'user_id' => $userId,
            'action' => 'created',
            'entity_type' => 'Project',
            'description' => 'Legacy activity',
            'metadata' => json_encode(['reason' => 'retention test']),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'tenant_id' => $tenantId,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        DB::table('notifications')->insert([
            'id' => Str::ulid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => 'system',
            'priority' => 'normal',
            'title' => 'Retention notice',
            'body' => 'Old notice',
            'channel' => 'inapp',
            'project_id' => $projectId,
            'data' => json_encode([]),
            'metadata' => json_encode([]),
            'event_key' => 'retention_test',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
