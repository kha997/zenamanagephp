<?php declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

/**
 * Regression guard for AUD-03 (docs/audits/2026-07-23-end-to-end-operational-audit.md):
 * proves Project::end_date advances by the correct number of calendar days via
 * the Carbon-parse-then-save pattern now used in ChangeRequestController::approve(),
 * instead of Eloquent's increment() (which threw a TypeError against the
 * `date`-cast attribute — Carbon + int is not a valid PHP operation).
 *
 * This test does NOT go through the ChangeRequest approval endpoint — it isolates
 * the exact assignment pattern the controller now uses, against the actual
 * `projects.end_date` DATE column, on this repo's real test driver (sqlite,
 * per .env.testing).
 */
class AudChangeRequestEndDateIncrementTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    /**
     * Regression guard: exercises the real approve() endpoint (not just the
     * isolated Carbon pattern above) with a non-zero approved_schedule_days,
     * so a reintroduction of the old increment() bug would actually fail a
     * test. The only other endpoint-level test touching approve()
     * (tests/Feature/Api/IntegrationTest.php) passes approved_schedule_days
     * => 0, which the controller's falsy guard (`if ($request->input(...))`)
     * skips entirely -- so it never reaches the buggy line.
     */
    public function test_approve_endpoint_with_schedule_days_actually_advances_end_date(): void
    {
        $tenant = Tenant::factory()->create();

        $approver = $this->createTenantUser($tenant, [], ['admin'], [
            'change-request.view',
            'change-request.approve',
            'change-request.reject',
        ]);

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $approver->id,
            'end_date' => '2026-07-23',
        ]);

        $requester = User::factory()->create(['tenant_id' => $tenant->id]);

        $cr = ChangeRequest::create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'title' => 'Schedule-extending CR',
            'description' => 'Proving end_date advances via the real approve endpoint.',
            'change_type' => 'schedule',
            'priority' => 'medium',
            'status' => ChangeRequest::STATUS_SUBMITTED,
            'requested_by' => (string) $requester->id,
            'assigned_to' => (string) $approver->id,
            'change_number' => 'CR-AUD03-ENDPOINT-001',
            'requested_at' => now(),
        ]);

        $token = $this->apiLoginToken($approver, $tenant);
        $headers = $this->authHeadersForUser($approver, $token);

        $this->withHeaders($headers)
            ->postJson(
                route('api.zena.change-requests.approve', ['id' => (string) $cr->id], false),
                ['approved_schedule_days' => 7]
            )
            ->assertOk();

        $raw = \DB::table('projects')->where('id', $project->id)->value('end_date');
        $rawDate = substr($raw, 0, 10);

        $this->assertSame('2026-07-30', $rawDate, 'Expected end_date to advance by exactly 7 calendar days via the real approve endpoint.');
    }

    public function test_increment_on_date_column_does_not_add_calendar_days(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'end_date' => '2026-07-23',
        ]);

        $project->end_date = \Carbon\Carbon::parse($project->end_date)->addDays(7);
        $project->save();

        $raw = \DB::table('projects')->where('id', $project->id)->value('end_date');
        // SQLite may return '2026-07-30 00:00:00', MySQL may return '2026-07-30' — extract just the date part
        $rawDate = substr($raw, 0, 10);

        $this->assertSame('2026-07-30', $rawDate, 'Expected end_date to advance by exactly 7 calendar days.');
    }
}
