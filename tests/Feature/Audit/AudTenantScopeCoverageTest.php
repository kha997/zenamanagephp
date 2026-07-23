<?php declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\Ncr;
use App\Models\Project;
use App\Models\QcPlan;
use App\Models\SiteDiary;
use App\Models\Tenant;
use App\Models\TaskAssignment;
use App\Models\TaskDependency;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EVIDENCE TEST — not a regression guard when first written, promoted to one
 * once the fix lands. Verifies AUD-05 from
 * docs/audits/2026-07-23-end-to-end-operational-audit.md.
 *
 * Scope note (established during fix planning, not in the original audit):
 * of the original 8 models named in AUD-05, only these 5
 * (QcPlan, Ncr, SiteDiary, TaskDependency, TaskAssignment) have a `tenant_id`
 * column in their actual migrated schema. MaterialRequest, ProjectPhase, and
 * ProjectMilestone do not have the column at all (confirmed via
 * Schema::getColumnListing()) and are deferred to a separate schema-migration
 * effort -- see docs/audits/2026-07-23-end-to-end-operational-audit.md.
 */
class AudTenantScopeCoverageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, class-string> */
    public static function modelsUnderTest(): array
    {
        return [
            'QcPlan' => QcPlan::class,
            'Ncr' => Ncr::class,
            'SiteDiary' => SiteDiary::class,
            'TaskDependency' => TaskDependency::class,
            'TaskAssignment' => TaskAssignment::class,
        ];
    }

    public function test_all_five_models_have_tenant_scope_registered(): void
    {
        foreach (self::modelsUnderTest() as $label => $class) {
            /** @var \Illuminate\Database\Eloquent\Model $instance */
            $instance = new $class();
            $registered = $instance->getGlobalScopes();
            $hasTenantScope = array_key_exists('tenant', $registered);

            $this->assertTrue($hasTenantScope, "{$label} should have TenantScope registered after the fix.");
        }
    }

    public function test_qc_plan_cross_tenant_leak_is_closed(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $projectA = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $projectB = Project::factory()->create(['tenant_id' => $tenantB->id]);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        QcPlan::create([
            'project_id' => $projectA->id,
            'tenant_id' => $tenantA->id,
            'title' => 'Tenant A plan',
            'status' => 'draft',
            'created_by' => $userA->id,
        ]);
        QcPlan::create([
            'project_id' => $projectB->id,
            'tenant_id' => $tenantB->id,
            'title' => 'Tenant B plan',
            'status' => 'draft',
            'created_by' => $userB->id,
        ]);

        app()->instance('current_tenant_id', $tenantA->id);

        $visible = QcPlan::all()->pluck('title')->all();

        $this->assertContains('Tenant A plan', $visible);
        $this->assertNotContains('Tenant B plan', $visible, 'TenantScope should hide the other tenant\'s QcPlan row.');
    }

    public function test_task_dependency_cross_tenant_leak_is_closed(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $taskA1 = Task::factory()->create(['tenant_id' => $tenantA->id]);
        $taskA2 = Task::factory()->create(['tenant_id' => $tenantA->id]);
        $taskB1 = Task::factory()->create(['tenant_id' => $tenantB->id]);
        $taskB2 = Task::factory()->create(['tenant_id' => $tenantB->id]);

        TaskDependency::create([
            'task_id' => $taskA1->id,
            'dependency_id' => $taskA2->id,
            'tenant_id' => $tenantA->id,
        ]);
        TaskDependency::create([
            'task_id' => $taskB1->id,
            'dependency_id' => $taskB2->id,
            'tenant_id' => $tenantB->id,
        ]);

        app()->instance('current_tenant_id', $tenantA->id);

        $this->assertSame(1, TaskDependency::count(), 'TenantScope should hide tenant B\'s dependency row.');
    }
}
