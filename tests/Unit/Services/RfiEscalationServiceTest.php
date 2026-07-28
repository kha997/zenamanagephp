<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\RfiEscalationConflictException;
use App\Exceptions\RfiEscalationNotFoundException;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\RfiEscalation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RfiEscalationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiEscalationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RfiEscalationService $service;
    protected Rfi $rfi;
    protected User $escalator;
    protected User $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RfiEscalationService::class);

        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $this->escalator = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->target = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->rfi = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'Test RFI',
            'subject' => 'Test RFI', 'description' => 'desc', 'question' => 'What is the spec?',
            'priority' => 'medium', 'status' => 'open', 'asked_by' => $this->escalator->id,
            'created_by' => $this->escalator->id, 'rfi_number' => 'TST-RFI-0002',
        ]);
    }

    public function test_escalate_creates_unresolved_escalation_and_updates_pointer_and_mirror(): void
    {
        $escalation = $this->service->escalate($this->rfi, $this->target->id, $this->escalator->id, 'Needs urgent answer');

        $this->assertNull($escalation->resolved_at);
        $this->assertSame($this->target->id, $escalation->escalated_to);

        $this->rfi->refresh();
        $this->assertSame($escalation->id, $this->rfi->current_escalation_id);
        $this->assertSame($this->target->id, $this->rfi->escalated_to);
        $this->assertSame('Needs urgent answer', $this->rfi->escalation_reason);
        $this->rfi->assertEscalationPointerIntegrity();
    }

    public function test_escalate_throws_conflict_when_active_escalation_already_exists(): void
    {
        $this->service->escalate($this->rfi, $this->target->id, $this->escalator->id, 'First escalation');

        $this->expectException(RfiEscalationConflictException::class);

        $this->service->escalate($this->rfi->fresh(), $this->target->id, $this->escalator->id, 'Second escalation');
    }

    public function test_has_active_escalation_reflects_current_state(): void
    {
        $this->assertFalse($this->service->hasActiveEscalation($this->rfi->id));

        $this->service->escalate($this->rfi, $this->target->id, $this->escalator->id, 'Urgent');

        $this->assertTrue($this->service->hasActiveEscalation($this->rfi->id));
    }

    public function test_service_never_reads_or_writes_rfi_status(): void
    {
        $reflection = new \ReflectionClass(\App\Services\RfiEscalationService::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertStringNotContainsString("'status'", $source, 'RfiEscalationService must not reference the rfis.status column — lifecycle belongs to RfiLifecycleService.');
    }

    public function test_resolve_escalation_sets_resolution_fields_once_and_clears_pointer(): void
    {
        $this->service->escalate($this->rfi, $this->target->id, $this->escalator->id, 'Urgent');

        $resolved = $this->service->resolveEscalation($this->rfi->fresh(), $this->target->id, 'Answered directly with the client');

        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame($this->target->id, $resolved->resolved_by);
        $this->assertSame('Answered directly with the client', $resolved->resolution);
        $this->assertSame(RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED, $resolved->resolution_type);

        $this->rfi->refresh();
        $this->assertNull($this->rfi->current_escalation_id);
        $this->assertNull($this->rfi->escalated_to);
        $this->assertNull($this->rfi->escalation_reason);
    }

    public function test_resolve_escalation_twice_throws_conflict(): void
    {
        $this->service->escalate($this->rfi, $this->target->id, $this->escalator->id, 'Urgent');
        $this->service->resolveEscalation($this->rfi->fresh(), $this->target->id, 'First resolution');

        $this->expectException(RfiEscalationConflictException::class);

        $this->service->resolveEscalation($this->rfi->fresh(), $this->target->id, 'Second attempt');
    }

    public function test_resolve_escalation_without_active_escalation_throws_not_found(): void
    {
        $this->expectException(RfiEscalationNotFoundException::class);

        $this->service->resolveEscalation($this->rfi, $this->escalator->id, 'No escalation to resolve');
    }

    public function test_resolve_escalation_rejects_corrupted_pointer_via_integrity_guard(): void
    {
        $otherRfi = Rfi::create([
            'tenant_id' => $this->rfi->tenant_id, 'project_id' => $this->rfi->project_id,
            'title' => 'Other RFI', 'subject' => 'Other RFI', 'description' => 'd',
            'question' => 'What about this?', 'priority' => 'medium', 'status' => 'open',
            'asked_by' => $this->escalator->id, 'created_by' => $this->escalator->id,
            'rfi_number' => 'TST-RFI-0099',
        ]);
        $escalationForOther = $this->service->escalate($otherRfi, $this->target->id, $this->escalator->id, 'Belongs to otherRfi');

        // Corrupt this->rfi's pointer to point at otherRfi's escalation.
        $this->rfi->update(['current_escalation_id' => $escalationForOther->id]);

        $this->expectException(\App\Exceptions\RfiEscalationIntegrityException::class);
        $this->service->resolveEscalation($this->rfi->fresh(), $this->target->id, 'Should be rejected by integrity guard');
    }
}
