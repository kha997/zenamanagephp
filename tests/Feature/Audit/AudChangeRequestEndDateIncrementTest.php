<?php declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
