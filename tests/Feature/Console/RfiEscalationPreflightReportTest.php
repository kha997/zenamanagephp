<?php declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiEscalationPreflightReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_lists_escalated_rows_and_anomalous_pending_rows(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $assignee = User::factory()->create(['tenant_id' => $tenant->id]);

        $escalatedWithAssignee = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'A',
            'subject' => 'S', 'description' => 'd', 'question' => 'q?', 'priority' => 'medium', 'asked_by' => $user->id, 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0001', 'status' => 'escalated', 'assigned_to' => $assignee->id,
        ]);
        $escalatedWithoutAssignee = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'B',
            'subject' => 'S', 'description' => 'd', 'question' => 'q?', 'priority' => 'medium', 'asked_by' => $user->id, 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0002', 'status' => 'escalated',
        ]);
        $pendingAnomaly = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'C',
            'subject' => 'S', 'description' => 'd', 'question' => 'q?', 'priority' => 'medium', 'asked_by' => $user->id, 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0003', 'status' => 'pending',
        ]);
        $closedWithSnapshot = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'D',
            'subject' => 'S', 'description' => 'd', 'question' => 'q?', 'priority' => 'medium', 'asked_by' => $user->id, 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0004', 'status' => 'closed',
            'escalated_to' => $assignee->id, 'escalated_by' => $user->id,
            'escalated_at' => now(), 'escalation_reason' => 'old escalation before close overwrote status',
        ]);
        Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'E',
            'subject' => 'S', 'description' => 'd', 'question' => 'q?', 'priority' => 'medium', 'asked_by' => $user->id, 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0005', 'status' => 'open',
        ]);

        $outputPath = storage_path('app/test-preflight-report.csv');
        @unlink($outputPath);

        $this->artisan('rfi:escalation-preflight-report', ['--output' => $outputPath])->assertExitCode(0);

        $this->assertFileExists($outputPath);
        $contents = file_get_contents($outputPath);

        $this->assertStringContainsString($escalatedWithAssignee->id, $contents);
        $this->assertStringContainsString($escalatedWithoutAssignee->id, $contents);
        $this->assertStringContainsString($pendingAnomaly->id, $contents);
        $this->assertStringContainsString($closedWithSnapshot->id, $contents);
        $this->assertStringNotContainsString('T-RFI-0005', $contents);

        @unlink($outputPath);
    }
}
