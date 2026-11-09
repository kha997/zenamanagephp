<?php

declare(strict_types=1);

namespace Tests\Unit\Factories;

use App\Models\Project;
use Database\Factories\ProjectFactory;
use Faker\Generator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GAP-116 G-1 — project factory dates must never break the "end after start" rule (ProjectFormRequest
 * `after:start_date`), or API tests that resubmit a factory project fail at random.
 *
 * - Structural check: a recording Faker captures the ranges each state passes to dateTimeBetween(); the latest
 *   possible start must fall on an earlier calendar day than the earliest possible end. This is deterministic and
 *   fails on the overlapping ranges.
 * - Sample check: 200 made projects per state, end day after start day; overdue ends before today.
 * - No frozen clock: Faker resolves relative dates against the real clock, so the assertions use it too.
 */
class ProjectFactoryDatesTest extends TestCase
{
    private const NO_RELATIONS = ['tenant_id' => 'tenant', 'pm_id' => null, 'created_by' => null];

    public function test_date_ranges_never_overlap_for_any_state(): void
    {
        foreach (['default' => null, 'completed' => 'completed', 'overdue' => 'overdue'] as $label => $state) {
            $faker = new RecordingFaker();
            foreach (\Faker\Factory::create()->getProviders() as $provider) {
                $faker->addProvider($provider);
            }
            $this->app->bind(Generator::class, static fn () => $faker);

            $factory = ProjectFactory::new();
            ($state === null ? $factory : $factory->{$state}())->make(self::NO_RELATIONS);

            $ranges = $faker->ranges;
            $this->assertGreaterThanOrEqual(2, count($ranges), $label);
            // Each state lists start_date before end_date; the last two calls are the effective ranges (a state that
            // overrides both dates comes after the default definition).
            $start = $ranges[count($ranges) - 2];
            $end = $ranges[count($ranges) - 1];
            $latestStart = Carbon::parse($start[1])->startOfDay();
            $earliestEnd = Carbon::parse($end[0])->startOfDay();
            $this->assertTrue($latestStart->lt($earliestEnd), "{$label}: latest start {$latestStart->toDateString()} must be before earliest end {$earliestEnd->toDateString()}");
            if ($state === 'overdue') {
                $this->assertTrue(Carbon::parse($end[1])->startOfDay()->lt(Carbon::today()), 'overdue: end must be before today');
            }
        }
    }

    public function test_sampled_projects_end_after_they_start(): void
    {
        foreach (['default' => null, 'completed' => 'completed', 'overdue' => 'overdue'] as $label => $state) {
            $factory = Project::factory()->count(200);
            $projects = ($state === null ? $factory : $factory->{$state}())->make(self::NO_RELATIONS);
            foreach ($projects as $project) {
                $this->assertTrue(
                    $project->start_date->copy()->startOfDay()->lt($project->end_date->copy()->startOfDay()),
                    "{$label}: {$project->start_date->toDateString()} → {$project->end_date->toDateString()}",
                );
                if ($state === 'overdue') {
                    $this->assertTrue($project->end_date->copy()->startOfDay()->lt(Carbon::today()), 'overdue end before today');
                }
            }
        }
    }
}

/**
 * Faker that records every dateTimeBetween() range in call order and returns the range start.
 */
class RecordingFaker extends Generator
{
    /** @var list<array{0: string, 1: string}> */
    public array $ranges = [];

    public function dateTimeBetween($startDate = '-30 years', $endDate = 'now', $timezone = null)
    {
        $this->ranges[] = [
            $startDate instanceof \DateTimeInterface ? $startDate->format('Y-m-d H:i:s') : (string) $startDate,
            $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d H:i:s') : (string) $endDate,
        ];

        return new \DateTime($startDate instanceof \DateTimeInterface ? $startDate->format('Y-m-d H:i:s') : (string) $startDate);
    }
}
