<?php declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\RfiLegacyMigrationConfirmation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiConfirmLegacyEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_an_escalated_row_creates_unresolved_escalation_and_captures_full_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $rfi = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'A',
            'subject' => 'A', 'question' => 'q', 'asked_by' => $user->id,
            'description' => 'd', 'priority' => 'medium', 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0010', 'status' => 'escalated', 'assigned_to' => $user->id,
            'escalated_to' => $user->id, 'escalated_by' => $user->id, 'escalated_at' => now(),
            'escalation_reason' => 'legacy reason',
        ]);

        $this->artisan('rfi:confirm-legacy-escalation', [
            'rfi_id' => $rfi->id,
            '--lifecycle' => 'in_progress',
            '--escalation' => 'unresolved',
            '--confirmed-by' => $user->id,
            '--reason' => 'Confirmed with the assignee over Slack that this RFI is still actively being escalated.',
        ])->assertExitCode(0);

        $confirmation = RfiLegacyMigrationConfirmation::where('rfi_id', $rfi->id)->first();
        $this->assertNotNull($confirmation);
        $this->assertSame($user->id, $confirmation->confirmed_by);
        $this->assertNotNull($confirmation->confirmed_at);
        $this->assertSame('in_progress', $confirmation->confirmed_lifecycle_status);
        $this->assertSame('unresolved', $confirmation->confirmed_escalation_state);
        $this->assertSame('Confirmed with the assignee over Slack that this RFI is still actively being escalated.', $confirmation->reason);

        $this->assertNotNull($confirmation->source_snapshot);
        $snapshot = json_decode($confirmation->source_snapshot, true);
        $this->assertSame('escalated', $snapshot['status']);
        $this->assertSame($user->id, $snapshot['assigned_to']);
        $this->assertSame('legacy reason', $snapshot['escalation_reason']);

        $rfi->refresh();
        $this->assertSame('in_progress', $rfi->status);
        $this->assertNotNull($rfi->current_escalation_id);

        $escalation = \App\Models\RfiEscalation::where('rfi_id', $rfi->id)->first();
        $this->assertNull($escalation->resolved_at);
    }

    public function test_confirmation_requires_a_reason(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $rfi = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'B',
            'subject' => 'B', 'question' => 'q', 'asked_by' => $user->id,
            'description' => 'd', 'priority' => 'medium', 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0011', 'status' => 'pending',
        ]);

        $this->artisan('rfi:confirm-legacy-escalation', [
            'rfi_id' => $rfi->id,
            '--lifecycle' => 'open',
            '--escalation' => 'none',
            '--confirmed-by' => $user->id,
        ])->assertExitCode(1);
    }
}
