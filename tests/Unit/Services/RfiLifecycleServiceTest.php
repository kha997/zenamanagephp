<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\RfiLifecycleTransitionException;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RfiEscalationService;
use App\Services\RfiLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private RfiLifecycleService $lifecycle;
    private RfiEscalationService $escalation;
    private User $user;
    private Tenant $tenant;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lifecycle = app(RfiLifecycleService::class);
        $this->escalation = app(RfiEscalationService::class);

        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeRfi(string $status, string $number): Rfi
    {
        return Rfi::create([
            'tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'title' => 'T',
            'subject' => 'S', 'description' => 'd', 'question' => 'q', 'priority' => 'medium', 'status' => $status,
            'asked_by' => $this->user->id, 'created_by' => $this->user->id, 'rfi_number' => $number,
        ]);
    }

    public function test_is_terminal_true_only_for_closed_and_cancelled(): void
    {
        $this->assertFalse($this->lifecycle->isTerminal($this->makeRfi('open', 'T-0001')));
        $this->assertFalse($this->lifecycle->isTerminal($this->makeRfi('in_progress', 'T-0002')));
        $this->assertFalse($this->lifecycle->isTerminal($this->makeRfi('answered', 'T-0003')));
        $this->assertTrue($this->lifecycle->isTerminal($this->makeRfi('closed', 'T-0004')));
        $this->assertTrue($this->lifecycle->isTerminal($this->makeRfi('cancelled', 'T-0005')));
    }

    public function test_respond_succeeds_from_open_and_sets_answered(): void
    {
        $rfi = $this->makeRfi('open', 'T-0010');

        $updated = $this->lifecycle->respond($rfi, $this->user->id, 'The answer', 'answered');

        $this->assertSame('answered', $updated->status);
    }

    public function test_respond_rejected_when_closed(): void
    {
        $rfi = $this->makeRfi('closed', 'T-0011');

        $this->expectException(RfiLifecycleTransitionException::class);
        $this->lifecycle->respond($rfi, $this->user->id, 'Too late', 'answered');
    }

    public function test_respond_rejected_to_closed_while_escalation_active(): void
    {
        $rfi = $this->makeRfi('open', 'T-0011a');
        $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->escalation->escalate($rfi, $target->id, $this->user->id, 'Still open');

        $this->expectException(RfiLifecycleTransitionException::class);
        $this->lifecycle->respond($rfi->fresh(), $this->user->id, 'Trying to close', 'closed');
    }

    public function test_respond_to_answered_still_succeeds_while_escalation_active(): void
    {
        $rfi = $this->makeRfi('open', 'T-0011b');
        $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->escalation->escalate($rfi, $target->id, $this->user->id, 'Still open');

        $updated = $this->lifecycle->respond($rfi->fresh(), $this->user->id, 'Answering', 'answered');

        $this->assertSame('answered', $updated->status);
    }

    public function test_close_rejected_when_not_answered(): void
    {
        $rfi = $this->makeRfi('open', 'T-0012');

        $this->expectException(RfiLifecycleTransitionException::class);
        $this->lifecycle->close($rfi, $this->user->id);
    }

    public function test_close_rejected_while_escalation_active(): void
    {
        $rfi = $this->makeRfi('answered', 'T-0013');
        $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->escalation->escalate($rfi, $target->id, $this->user->id, 'Still open');

        $this->expectException(RfiLifecycleTransitionException::class);
        $this->lifecycle->close($rfi->fresh(), $this->user->id);
    }

    public function test_close_succeeds_when_answered_and_no_active_escalation(): void
    {
        $rfi = $this->makeRfi('answered', 'T-0014');

        $updated = $this->lifecycle->close($rfi, $this->user->id);

        $this->assertSame('closed', $updated->status);
    }

    public function test_cancel_without_active_escalation_succeeds(): void
    {
        $rfi = $this->makeRfi('open', 'T-0015');

        $updated = $this->lifecycle->cancel($rfi, $this->user->id, 'No longer needed');

        $this->assertSame('cancelled', $updated->status);
    }

    public function test_cancel_with_active_escalation_resolves_it_atomically(): void
    {
        $rfi = $this->makeRfi('open', 'T-0016');
        $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->escalation->escalate($rfi, $target->id, $this->user->id, 'Urgent');

        $updated = $this->lifecycle->cancel($rfi->fresh(), $this->user->id, 'Project cancelled');

        $this->assertSame('cancelled', $updated->status);
        $this->assertNull($updated->current_escalation_id);

        $escalation = \App\Models\RfiEscalation::where('rfi_id', $rfi->id)->first();
        $this->assertNotNull($escalation->resolved_at);
        $this->assertSame(\App\Models\RfiEscalation::RESOLUTION_TYPE_RFI_CANCELLED, $escalation->resolution_type);
    }

    public function test_cancel_rejected_on_terminal_rfi(): void
    {
        $rfi = $this->makeRfi('closed', 'T-0017');

        $this->expectException(RfiLifecycleTransitionException::class);
        $this->lifecycle->cancel($rfi, $this->user->id, 'Too late');
    }
}
