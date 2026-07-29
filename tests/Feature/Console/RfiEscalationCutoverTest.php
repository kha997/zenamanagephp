<?php declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\RfiLegacyMigrationConfirmation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiEscalationCutoverTest extends TestCase
{
    use RefreshDatabase;

    private function makeRfi(Tenant $tenant, Project $project, User $user, array $overrides = []): Rfi
    {
        return Rfi::create(array_merge([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'A',
            'subject' => 'A subject',
            'description' => 'd',
            'question' => 'q',
            'priority' => 'medium',
            'asked_by' => $user->id,
            'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0020',
            'status' => 'open',
        ], $overrides));
    }

    public function test_cutover_refuses_to_run_while_any_legacy_row_is_unconfirmed(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->makeRfi($tenant, $project, $user, [
            'rfi_number' => 'T-RFI-0020',
            'status' => 'escalated',
        ]);

        $this->artisan('rfi:escalation-cutover')->assertExitCode(1);

        $this->assertDatabaseMissing('rfi_escalation_migration_state', []);
    }

    public function test_cutover_succeeds_when_every_legacy_row_is_confirmed(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $rfi = $this->makeRfi($tenant, $project, $user, [
            'rfi_number' => 'T-RFI-0021',
            'status' => 'in_progress',
        ]);

        RfiLegacyMigrationConfirmation::create([
            'rfi_id' => $rfi->id,
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
            'confirmed_lifecycle_status' => 'in_progress',
            'confirmed_escalation_state' => 'none',
            'reason' => 'Row already had no escalation snapshot and a valid status.',
            'source_snapshot' => json_encode(['status' => 'in_progress']),
        ]);

        $this->artisan('rfi:escalation-cutover')->assertExitCode(0);

        $this->assertDatabaseCount('rfi_escalation_migration_state', 1);
    }

    public function test_cutover_refuses_when_a_row_has_escalation_snapshot_but_non_escalated_status(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->makeRfi($tenant, $project, $user, [
            'rfi_number' => 'T-RFI-0022',
            'status' => 'answered',
            'escalated_to' => $user->id,
        ]);

        $this->artisan('rfi:escalation-cutover')->assertExitCode(1);

        $this->assertDatabaseMissing('rfi_escalation_migration_state', []);
    }

    public function test_cutover_is_a_noop_when_there_are_no_legacy_rows_at_all(): void
    {
        $this->artisan('rfi:escalation-cutover')->assertExitCode(0);

        $this->assertDatabaseCount('rfi_escalation_migration_state', 1);
    }

    public function test_cutover_refuses_when_a_row_has_a_partial_snapshot_missing_escalated_to(): void
    {
        // A legacy row whose escalated_to was cleared/never populated but another
        // snapshot field survived (escalation_reason here) is still evidence of a
        // past escalation per spec §6.2's "4 field snapshot" check — the cutover
        // gate must not miss it just because escalated_to specifically is null.
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->makeRfi($tenant, $project, $user, [
            'rfi_number' => 'T-RFI-0023',
            'status' => 'answered',
            'escalated_to' => null,
            'escalation_reason' => 'Escalated to a user later deleted; snapshot partially cleared.',
        ]);

        $this->artisan('rfi:escalation-cutover')->assertExitCode(1);

        $this->assertDatabaseMissing('rfi_escalation_migration_state', []);
    }
}
