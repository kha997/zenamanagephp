<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Baseline;
use App\Models\Project;
use App\Services\ProjectDelayStatus;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pure evaluator — no DB, models are in-memory. Date comparisons are
 * date-only, so time-of-day must never change a verdict.
 */
class ProjectDelayStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function project(array $attributes): Project
    {
        $project = new Project();
        $project->forceFill($attributes);

        return $project;
    }

    private function baseline(array $attributes): Baseline
    {
        $baseline = new Baseline();
        $baseline->forceFill($attributes);

        return $baseline;
    }

    public function test_completed_project_has_no_delay_flag(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');

        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'completed', 'end_date' => '2026-01-01']),
            $this->baseline(['end_date' => '2025-12-01'])
        );

        $this->assertSame('completed', $result['state']);
        $this->assertNull($result['days_late']);
    }

    public function test_no_baseline(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');

        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'active', 'end_date' => '2026-08-01']),
            null
        );

        $this->assertSame('no_baseline', $result['state']);
        $this->assertNull($result['days_late']);
        $this->assertNull($result['baseline']);
    }

    public function test_late_when_today_is_past_committed_end(): void
    {
        Carbon::setTestNow('2026-07-19 23:59:00');

        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'active', 'end_date' => '2026-09-01']),
            $this->baseline(['end_date' => '2026-07-09'])
        );

        $this->assertSame('late', $result['state']);
        $this->assertSame(10, $result['days_late']);
    }

    public function test_forecast_late_when_current_end_moved_past_committed_end(): void
    {
        Carbon::setTestNow('2026-07-19 00:01:00');

        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'active', 'end_date' => '2026-09-15']),
            $this->baseline(['end_date' => '2026-08-31'])
        );

        $this->assertSame('forecast_late', $result['state']);
        $this->assertSame(15, $result['days_late']);
    }

    public function test_on_track(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');

        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'active', 'end_date' => '2026-08-20']),
            $this->baseline(['end_date' => '2026-08-31'])
        );

        $this->assertSame('on_track', $result['state']);
        $this->assertNull($result['days_late']);
    }

    public function test_project_without_current_end_date_skips_forecast_rule(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');

        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'active', 'end_date' => null]),
            $this->baseline(['end_date' => '2026-08-31'])
        );

        $this->assertSame('on_track', $result['state']);
    }

    public function test_datetime_strings_compare_date_only(): void
    {
        Carbon::setTestNow('2026-07-19 00:00:01');

        // Committed end is "today" with a later time-of-day — NOT late.
        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'active', 'end_date' => '2026-07-19 18:00:00']),
            $this->baseline(['end_date' => '2026-07-19 23:00:00'])
        );

        $this->assertSame('on_track', $result['state']);
    }
}
