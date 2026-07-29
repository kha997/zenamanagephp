<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Exceptions\RfiEscalationIntegrityException;
use App\Models\Rfi;
use App\Models\RfiEscalation;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiTest extends TestCase
{
    use RefreshDatabase;

    private function makeRfi(Tenant $tenant, Project $project, User $user, string $rfiNumber): Rfi
    {
        return Rfi::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Test RFI',
            'subject' => 'Test RFI',
            'description' => 'desc',
            'question' => 'What is the spec?',
            'priority' => 'medium',
            'status' => 'open',
            'asked_by' => $user->id,
            'created_by' => $user->id,
            'rfi_number' => $rfiNumber,
        ]);
    }

    public function test_current_escalation_relation_resolves_to_the_linked_escalation(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $rfi = $this->makeRfi($tenant, $project, $user, 'TST-RFI-0001');

        $this->assertNull($rfi->current_escalation_id);
        $this->assertNull($rfi->currentEscalation);

        $escalation = RfiEscalation::create([
            'rfi_id' => $rfi->id, 'tenant_id' => $tenant->id,
            'escalated_to' => $user->id, 'escalated_by' => $user->id,
            'escalated_at' => now(), 'escalation_reason' => 'Urgent',
        ]);
        $rfi->update(['current_escalation_id' => $escalation->id]);
        $rfi->refresh();

        $this->assertSame($escalation->id, $rfi->currentEscalation->id);
    }

    public function test_assert_pointer_integrity_passes_when_null(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $rfi = $this->makeRfi($tenant, $project, $user, 'TST-RFI-0002');

        $rfi->assertEscalationPointerIntegrity();
        $this->addToAssertionCount(1);
    }

    public function test_assert_pointer_integrity_rejects_pointer_to_another_rfis_escalation(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $rfiA = $this->makeRfi($tenant, $project, $user, 'TST-RFI-0003');
        $rfiB = $this->makeRfi($tenant, $project, $user, 'TST-RFI-0004');

        $escalationForB = RfiEscalation::create([
            'rfi_id' => $rfiB->id, 'tenant_id' => $tenant->id,
            'escalated_to' => $user->id, 'escalated_by' => $user->id,
            'escalated_at' => now(), 'escalation_reason' => 'Urgent',
        ]);

        // Simulate a corrupted pointer (bypassing the service) to prove the guard catches it.
        $rfiA->current_escalation_id = $escalationForB->id;

        $this->expectException(RfiEscalationIntegrityException::class);
        $rfiA->assertEscalationPointerIntegrity();
    }

    public function test_assert_pointer_integrity_rejects_pointer_to_another_tenants_escalation(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $projectA = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $rfiA = $this->makeRfi($tenantA, $projectA, $userA, 'TST-RFI-0005');

        $foreignEscalation = RfiEscalation::create([
            'rfi_id' => $rfiA->id, 'tenant_id' => $tenantB->id, // deliberately wrong tenant
            'escalated_to' => $userB->id, 'escalated_by' => $userB->id,
            'escalated_at' => now(), 'escalation_reason' => 'Cross-tenant corruption',
        ]);
        $rfiA->current_escalation_id = $foreignEscalation->id;

        $this->expectException(RfiEscalationIntegrityException::class);
        $rfiA->assertEscalationPointerIntegrity();
    }

    public function test_assert_pointer_integrity_rejects_pointer_to_already_resolved_escalation(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $rfi = $this->makeRfi($tenant, $project, $user, 'TST-RFI-0006');

        $resolved = RfiEscalation::create([
            'rfi_id' => $rfi->id, 'tenant_id' => $tenant->id,
            'escalated_to' => $user->id, 'escalated_by' => $user->id,
            'escalated_at' => now(), 'escalation_reason' => 'Urgent',
            'resolved_at' => now(), 'resolved_by' => $user->id,
            'resolution' => 'done', 'resolution_type' => RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED,
        ]);
        $rfi->current_escalation_id = $resolved->id;

        $this->expectException(RfiEscalationIntegrityException::class);
        $rfi->assertEscalationPointerIntegrity();
    }
}
