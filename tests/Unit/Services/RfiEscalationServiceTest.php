<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\RfiEscalationConflictException;
use App\Models\Project;
use App\Models\Rfi;
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
}
