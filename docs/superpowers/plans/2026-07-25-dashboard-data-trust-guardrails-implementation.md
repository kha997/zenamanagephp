# Dashboard Data Trust Guardrails Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add trust-state metadata (`availability`/`reliability`/`freshness`) to 6 dashboard metrics (4 in `Api\PmDashboardController::getProjectProgress()`, 1 in `PortalDashboardController`, 2 in `CrmReportController`) so the UI can distinguish real zero, no data, not applicable, error, and partial-reliability values — without changing any existing legacy field's value or breaking any existing consumer.

**Architecture:** A small `App\Support\Dashboard` namespace holds 3 string-backed enums (`Availability`, `Reliability`, `Freshness`) and 2 plain classes: `MetricResult` (immutable value object with a `toArray()` serializer) and `MetricGuard` (a private-to-Phase-1 static helper that wraps a metric computation closure in try/catch, logs on failure with a correlation ID, and returns an `ERROR` `MetricResult`). Each controller builds `MetricResult` objects directly inside new private `compute*Meta()` methods — no registry, no rule engine, no shared interface across controllers. `Api\PmDashboardController` adds 4 new sibling `*_meta` JSON fields next to its 4 existing legacy fields (which are never modified). `PortalDashboardController` and `CrmReportController` are pure server-rendered Blade with no JSON/AJAX/chart/export consumer (confirmed by evidence closure in the spec, rev 4) so their views consume `MetricResult` directly — no sibling field needed there.

**Tech Stack:** Laravel (PHP 8.1+ enums), PHPUnit (`RefreshDatabase`), existing `Tests\Traits\TenantUserFactoryTrait` / `AuthenticationTestTrait` test helpers, existing `App\Services\ErrorEnvelopeService::getCurrentRequestId()` correlation-ID convention.

## Global Constraints

- Source spec: `docs/superpowers/specs/2026-07-25-dashboard-data-trust-guardrails-design.md` (rev 4, commit `c81ee2c1`, `DESIGN APPROVED`). Every task below implements a specific section of that spec; section numbers are cited per task.
- **Never modify the value or fallback behavior of the 4 existing legacy fields** (`overall_progress`, `milestone_progress`, `budget_progress`, `timeline_progress`) in `PmDashboardController::getProjectProgress()`. The existing methods `computeOverallProgress()`, `computeTaskProgress()`, `computeMilestoneProgress()`, `computeBudgetProgress()`, `computeTimelineProgress()` must not be edited at all — new `compute*Meta()` methods are added alongside them, never replacing them.
- **`*_meta.value` must equal the corresponding legacy sub-field exactly when `availability=AVAILABLE`** (spec §3.2 rule 4) — enforced by an assertion in every AVAILABLE-path test.
- **`*_meta.value` is `null` whenever `availability != AVAILABLE`** — never `0` as a stand-in for missing/errored data (spec §8.3).
- **No new database tables, no registry, no generic "rule engine".** `MetricResult`/`MetricGuard` are plain PHP classes used directly by the 3 controllers touched in this plan — nothing else may depend on them in Phase 1.
- **`Freshness` is always `Freshness::UNKNOWN` in Phase 1.** No threshold logic is implemented (spec §3, Phase 1 freshness rule).
- **`ERROR` never substitutes for a request-level error.** Auth/permission/tenant/infrastructure failures keep their existing HTTP status codes (401/403/404/500) exactly as today — `MetricGuard` only wraps the per-metric computation closures, never the whole controller action (spec §8.1).
- **Do not touch** `Project.progress` (`ProjectManagerController`), `AnalyticsController::avgProgress`, `ReportPageController` cashflow, `Api\ProjectAnalyticsController`, Milestone write API, or `ContractPayment`/`payment_certificates` schema — all explicitly out of scope (spec §11 Rollout, "Không refactor ngoài 6 widget Phase 1", "Không khôi phục Milestone API").
- Money/label copy is Vietnamese and must match the spec's exact wording where the spec quotes it verbatim (payment labels/explanations in spec §7.1/§7.2).

---

## Task 1: `Availability`, `Reliability`, `Freshness` enums

**Files:**
- Create: `app/Support/Dashboard/Availability.php`
- Create: `app/Support/Dashboard/Reliability.php`
- Create: `app/Support/Dashboard/Freshness.php`
- Test: `tests/Unit/Support/Dashboard/DashboardEnumsTest.php`

**Interfaces:**
- Produces: `App\Support\Dashboard\Availability` (cases `AVAILABLE`, `NO_DATA`, `NOT_APPLICABLE`, `ERROR`, each a string-backed case whose `->value` equals its own name), `App\Support\Dashboard\Reliability` (cases `RELIABLE`, `LIMITED`, `LEGACY`, `UNKNOWN`), `App\Support\Dashboard\Freshness` (cases `CURRENT`, `STALE`, `UNKNOWN`) — all consumed by Task 2's `MetricResult`.

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard;

use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\Reliability;
use PHPUnit\Framework\TestCase;

class DashboardEnumsTest extends TestCase
{
    public function test_availability_has_exactly_four_string_backed_cases(): void
    {
        $this->assertSame('AVAILABLE', Availability::AVAILABLE->value);
        $this->assertSame('NO_DATA', Availability::NO_DATA->value);
        $this->assertSame('NOT_APPLICABLE', Availability::NOT_APPLICABLE->value);
        $this->assertSame('ERROR', Availability::ERROR->value);
        $this->assertCount(4, Availability::cases());
    }

    public function test_reliability_has_exactly_four_string_backed_cases(): void
    {
        $this->assertSame('RELIABLE', Reliability::RELIABLE->value);
        $this->assertSame('LIMITED', Reliability::LIMITED->value);
        $this->assertSame('LEGACY', Reliability::LEGACY->value);
        $this->assertSame('UNKNOWN', Reliability::UNKNOWN->value);
        $this->assertCount(4, Reliability::cases());
    }

    public function test_freshness_has_exactly_three_string_backed_cases(): void
    {
        $this->assertSame('CURRENT', Freshness::CURRENT->value);
        $this->assertSame('STALE', Freshness::STALE->value);
        $this->assertSame('UNKNOWN', Freshness::UNKNOWN->value);
        $this->assertCount(3, Freshness::cases());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Support/Dashboard/DashboardEnumsTest.php`
Expected: FAIL with "Class App\Support\Dashboard\Availability not found" (or equivalent autoload error for all three).

- [ ] **Step 3: Write the enums**

`app/Support/Dashboard/Availability.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Dashboard;

enum Availability: string
{
    case AVAILABLE = 'AVAILABLE';
    case NO_DATA = 'NO_DATA';
    case NOT_APPLICABLE = 'NOT_APPLICABLE';
    case ERROR = 'ERROR';
}
```

`app/Support/Dashboard/Reliability.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Dashboard;

enum Reliability: string
{
    case RELIABLE = 'RELIABLE';
    case LIMITED = 'LIMITED';
    case LEGACY = 'LEGACY';
    case UNKNOWN = 'UNKNOWN';
}
```

`app/Support/Dashboard/Freshness.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Dashboard;

enum Freshness: string
{
    case CURRENT = 'CURRENT';
    case STALE = 'STALE';
    case UNKNOWN = 'UNKNOWN';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Support/Dashboard/DashboardEnumsTest.php`
Expected: PASS (3 tests, 11 assertions).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Dashboard/Availability.php app/Support/Dashboard/Reliability.php app/Support/Dashboard/Freshness.php tests/Unit/Support/Dashboard/DashboardEnumsTest.php
git commit -m "feat(dashboard): add Availability/Reliability/Freshness trust-state enums"
```

---

## Task 2: `MetricResult` value object

**Files:**
- Create: `app/Support/Dashboard/MetricResult.php`
- Test: `tests/Unit/Support/Dashboard/MetricResultTest.php`

**Interfaces:**
- Consumes: `App\Support\Dashboard\{Availability, Reliability, Freshness}` (Task 1).
- Produces: `App\Support\Dashboard\MetricResult` — constructor `__construct(mixed $value, Availability $availability, Reliability $reliability, Freshness $freshness, ?\Carbon\Carbon $asOf, string $label, ?string $explanation)`, method `toArray(): array` returning `['value' => mixed, 'availability' => string, 'reliability' => string, 'freshness' => string, 'as_of' => ?string, 'label' => string, 'explanation' => ?string]`. Consumed by Task 3 (`MetricGuard`) and every controller task (4–10).

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard;

use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\MetricResult;
use App\Support\Dashboard\Reliability;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class MetricResultTest extends TestCase
{
    public function test_to_array_serializes_enums_to_their_string_value_and_carbon_to_iso8601(): void
    {
        $asOf = Carbon::create(2026, 7, 25, 9, 14, 0, 'Asia/Ho_Chi_Minh');

        $result = new MetricResult(
            value: 40.0,
            availability: Availability::AVAILABLE,
            reliability: Reliability::RELIABLE,
            freshness: Freshness::UNKNOWN,
            asOf: $asOf,
            label: 'Tiến độ công việc (Task)',
            explanation: null,
        );

        $this->assertSame([
            'value' => 40.0,
            'availability' => 'AVAILABLE',
            'reliability' => 'RELIABLE',
            'freshness' => 'UNKNOWN',
            'as_of' => $asOf->toIso8601String(),
            'label' => 'Tiến độ công việc (Task)',
            'explanation' => null,
        ], $result->toArray());
    }

    public function test_to_array_serializes_null_value_and_null_as_of(): void
    {
        $result = new MetricResult(
            value: null,
            availability: Availability::NO_DATA,
            reliability: Reliability::RELIABLE,
            freshness: Freshness::UNKNOWN,
            asOf: null,
            label: 'Tiến độ công việc (Task)',
            explanation: 'Dự án chưa có công việc (Task) nào được tạo.',
        );

        $this->assertNull($result->toArray()['value']);
        $this->assertNull($result->toArray()['as_of']);
        $this->assertSame('NO_DATA', $result->toArray()['availability']);
        $this->assertSame('Dự án chưa có công việc (Task) nào được tạo.', $result->toArray()['explanation']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Support/Dashboard/MetricResultTest.php`
Expected: FAIL with "Class App\Support\Dashboard\MetricResult not found".

- [ ] **Step 3: Write the class**

`app/Support/Dashboard/MetricResult.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Dashboard;

use Carbon\Carbon;

final class MetricResult
{
    public function __construct(
        public readonly mixed $value,
        public readonly Availability $availability,
        public readonly Reliability $reliability,
        public readonly Freshness $freshness,
        public readonly ?Carbon $asOf,
        public readonly string $label,
        public readonly ?string $explanation,
    ) {
    }

    /**
     * @return array{value: mixed, availability: string, reliability: string, freshness: string, as_of: string|null, label: string, explanation: string|null}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'availability' => $this->availability->value,
            'reliability' => $this->reliability->value,
            'freshness' => $this->freshness->value,
            'as_of' => $this->asOf?->toIso8601String(),
            'label' => $this->label,
            'explanation' => $this->explanation,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Support/Dashboard/MetricResultTest.php`
Expected: PASS (2 tests, 7 assertions).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Dashboard/MetricResult.php tests/Unit/Support/Dashboard/MetricResultTest.php
git commit -m "feat(dashboard): add MetricResult value object"
```

---

## Task 3: `MetricGuard` — per-metric try/catch + logging helper

**Files:**
- Create: `app/Support/Dashboard/MetricGuard.php`
- Test: `tests/Unit/Support/Dashboard/MetricGuardTest.php`

**Interfaces:**
- Consumes: `App\Support\Dashboard\{Availability, Reliability, Freshness, MetricResult}` (Tasks 1–2), `App\Services\ErrorEnvelopeService::getCurrentRequestId(): ?string` (existing, confirmed at `app/Services/ErrorEnvelopeService.php:257-262`).
- Produces: `App\Support\Dashboard\MetricGuard::wrap(string $widget, array $logContext, string $label, \Closure $compute): MetricResult` — consumed by every `compute*Meta()` method in Tasks 4–10.

This is the spec §8.1–§8.4 implementation: it is a single small static helper shared only by the 6 Phase-1 widgets in this plan, not a generic engine or interface for arbitrary future widgets (spec §8.4 explicitly permits this as "thay đổi cục bộ, nhỏ").

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard;

use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\MetricGuard;
use App\Support\Dashboard\MetricResult;
use App\Support\Dashboard\Reliability;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class MetricGuardTest extends TestCase
{
    public function test_wrap_returns_the_closure_result_when_no_exception_is_thrown(): void
    {
        $happy = new MetricResult(
            value: 40.0,
            availability: Availability::AVAILABLE,
            reliability: Reliability::RELIABLE,
            freshness: Freshness::UNKNOWN,
            asOf: null,
            label: 'Tiến độ công việc (Task)',
            explanation: null,
        );

        $result = MetricGuard::wrap('overall_progress', [], 'Tiến độ công việc (Task)', fn () => $happy);

        $this->assertSame($happy, $result);
    }

    public function test_wrap_logs_and_returns_error_metric_result_when_closure_throws(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('dashboard_metric_error', \Mockery::on(function (array $context) {
                return $context['widget'] === 'overall_progress'
                    && $context['project_id'] === 'proj-123'
                    && $context['tenant_id'] === 'tenant-456'
                    && array_key_exists('request_id', $context)
                    && $context['exception'] === 'boom'
                    && $context['exception_class'] === RuntimeException::class;
            }));

        $result = MetricGuard::wrap(
            'overall_progress',
            ['project_id' => 'proj-123', 'tenant_id' => 'tenant-456'],
            'Tiến độ công việc (Task)',
            function () {
                throw new RuntimeException('boom');
            },
        );

        $this->assertNull($result->value);
        $this->assertSame(Availability::ERROR, $result->availability);
        $this->assertSame(Reliability::UNKNOWN, $result->reliability);
        $this->assertSame(Freshness::UNKNOWN, $result->freshness);
        $this->assertNull($result->asOf);
        $this->assertSame('Tiến độ công việc (Task)', $result->label);
        $this->assertNotNull($result->explanation);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Support/Dashboard/MetricGuardTest.php`
Expected: FAIL with "Class App\Support\Dashboard\MetricGuard not found".

- [ ] **Step 3: Write the class**

`app/Support/Dashboard/MetricGuard.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Services\ErrorEnvelopeService;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MetricGuard
{
    /**
     * @param array<string, mixed> $logContext
     */
    public static function wrap(string $widget, array $logContext, string $label, \Closure $compute): MetricResult
    {
        try {
            return $compute();
        } catch (Throwable $e) {
            Log::error('dashboard_metric_error', array_merge($logContext, [
                'widget' => $widget,
                'request_id' => ErrorEnvelopeService::getCurrentRequestId(),
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ]));

            return new MetricResult(
                value: null,
                availability: Availability::ERROR,
                reliability: Reliability::UNKNOWN,
                freshness: Freshness::UNKNOWN,
                asOf: null,
                label: $label,
                explanation: "Không thể tính được \"{$label}\" do lỗi truy vấn dữ liệu.",
            );
        }
    }
}
```

Note: `array_merge($logContext, [...])` puts `widget`/`request_id`/`exception`/`exception_class` AFTER `$logContext` so they cannot be accidentally overwritten by caller-supplied context keys of the same name.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Support/Dashboard/MetricGuardTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Dashboard/MetricGuard.php tests/Unit/Support/Dashboard/MetricGuardTest.php
git commit -m "feat(dashboard): add MetricGuard per-metric error handling helper"
```

---

## Task 4: `overall_progress_meta` on `PmDashboardController`

**Files:**
- Modify: `app/Http/Controllers/Api/PmDashboardController.php`
- Test: `tests/Feature/Api/PmDashboardApiTest.php`

**Interfaces:**
- Consumes: `App\Support\Dashboard\{Availability, Reliability, Freshness, MetricResult, MetricGuard}` (Tasks 1–3).
- Produces: JSON field `data.overall_progress_meta` on `GET api/zena/pm/progress` — `{value: float|null, availability, reliability, freshness, as_of, label, explanation}`.

Spec reference: §4 (Progress semantics), §3.4 (JSON sample), §8 (ERROR handling).

- [ ] **Step 1: Write the failing tests**

Add a new test method to `tests/Feature/Api/PmDashboardApiTest.php` (place it after `test_pm_progress_route_requires_project_id_and_hides_inaccessible_projects`, before the `private function createAssignedProject` block):

```php
    public function test_overall_progress_meta_is_no_data_when_project_has_no_tasks(): void
    {
        $project = $this->createAssignedProject('Empty progress project', Project::STATUS_ACTIVE);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.overall_progress', 0.0)
            ->assertJsonPath('data.overall_progress_meta.value', null)
            ->assertJsonPath('data.overall_progress_meta.availability', 'NO_DATA')
            ->assertJsonPath('data.overall_progress_meta.reliability', 'RELIABLE')
            ->assertJsonPath('data.overall_progress_meta.freshness', 'UNKNOWN')
            ->assertJsonPath('data.overall_progress_meta.as_of', null)
            ->assertJsonPath('data.overall_progress_meta.explanation', 'Dự án chưa có công việc (Task) nào được tạo.');
    }
```

Then edit the existing big happy-path test `test_pm_dashboard_exposes_full_progress_snapshot...` (the one asserting `data.overall_progress`, 25 etc. — find its exact name by reading the file, it's the method containing `->assertJsonPath('data.overall_progress', 25)`) and add these two lines immediately after `->assertJsonPath('data.overall_progress', 25)`:

```php
            ->assertJsonPath('data.overall_progress_meta.value', 25)
            ->assertJsonPath('data.overall_progress_meta.availability', 'AVAILABLE')
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/PmDashboardApiTest.php`
Expected: FAIL — `data.overall_progress_meta` key does not exist in the response.

- [ ] **Step 3: Implement `computeOverallProgressMeta()` and wire it into the response**

In `app/Http/Controllers/Api/PmDashboardController.php`, add these imports after the existing `use` block (after `use Illuminate\Support\Facades\Auth;`):

```php
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\MetricGuard;
use App\Support\Dashboard\MetricResult;
use App\Support\Dashboard\Reliability;
use Carbon\Carbon;
```

Change the `getProjectProgress()` response array — replace:

```php
        return $this->zenaSuccessResponse([
            'project' => [
                'id' => (string) $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
            'overall_progress' => $this->computeOverallProgress($projectId),
            'task_progress' => $this->computeTaskProgress($projectId),
            'milestone_progress' => $this->computeMilestoneProgress($projectId),
            'budget_progress' => $this->computeBudgetProgress($project),
            'timeline_progress' => $this->computeTimelineProgress($project),
        ]);
```

with:

```php
        return $this->zenaSuccessResponse([
            'project' => [
                'id' => (string) $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
            'overall_progress' => $this->computeOverallProgress($projectId),
            'overall_progress_meta' => $this->computeOverallProgressMeta($projectId)->toArray(),
            'task_progress' => $this->computeTaskProgress($projectId),
            'milestone_progress' => $this->computeMilestoneProgress($projectId),
            'budget_progress' => $this->computeBudgetProgress($project),
            'timeline_progress' => $this->computeTimelineProgress($project),
        ]);
```

Add this new private method directly after `computeOverallProgress()` (do not modify `computeOverallProgress()` itself):

```php
    private function computeOverallProgressMeta(string $projectId): MetricResult
    {
        $label = 'Tiến độ công việc (Task)';

        return MetricGuard::wrap(
            'overall_progress',
            ['project_id' => $projectId, 'tenant_id' => (string) Auth::user()?->tenant_id],
            $label,
            function () use ($projectId, $label) {
                $total = Task::where('project_id', $projectId)->count();

                if ($total === 0) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NO_DATA,
                        reliability: Reliability::RELIABLE,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Dự án chưa có công việc (Task) nào được tạo.',
                    );
                }

                $completed = Task::where('project_id', $projectId)
                    ->where('status', Task::STATUS_COMPLETED)
                    ->count();

                $value = round(($completed / $total) * 100, 2);
                $asOf = Task::where('project_id', $projectId)->max('updated_at');

                return new MetricResult(
                    value: $value,
                    availability: Availability::AVAILABLE,
                    reliability: Reliability::RELIABLE,
                    freshness: Freshness::UNKNOWN,
                    asOf: $asOf ? Carbon::parse($asOf) : null,
                    label: $label,
                    explanation: null,
                );
            },
        );
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/PmDashboardApiTest.php`
Expected: PASS, all tests in the file green (existing happy-path test still passes with its original `overall_progress: 25` assertion untouched, plus the two new `overall_progress_meta` assertions).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/PmDashboardController.php tests/Feature/Api/PmDashboardApiTest.php
git commit -m "feat(dashboard): add overall_progress_meta to PM progress endpoint"
```

---

## Task 5: `milestone_progress_meta` on `PmDashboardController`

**Files:**
- Modify: `app/Http/Controllers/Api/PmDashboardController.php`
- Test: `tests/Feature/Api/PmDashboardApiTest.php`

**Interfaces:**
- Consumes: same as Task 4, plus `App\Models\ProjectMilestone::STATUS_COMPLETED` (existing).
- Produces: JSON field `data.milestone_progress_meta` — `reliability` is **always** `LEGACY` regardless of `availability` (spec §6: "reliability = LEGACY ở cả hai nhánh").

Spec reference: §6 (Milestone semantics).

- [ ] **Step 1: Write the failing tests**

Add after the test added in Task 4:

```php
    public function test_milestone_progress_meta_is_no_data_and_legacy_when_project_has_no_milestones(): void
    {
        $project = $this->createAssignedProject('No milestone project', Project::STATUS_ACTIVE);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.milestone_progress_meta.value', null)
            ->assertJsonPath('data.milestone_progress_meta.availability', 'NO_DATA')
            ->assertJsonPath('data.milestone_progress_meta.reliability', 'LEGACY');
    }
```

Then edit the existing big happy-path test again, adding these lines immediately after `->assertJsonPath('data.milestone_progress.completion_rate', 33.33)`:

```php
            ->assertJsonPath('data.milestone_progress_meta.value', 33.33)
            ->assertJsonPath('data.milestone_progress_meta.availability', 'AVAILABLE')
            ->assertJsonPath('data.milestone_progress_meta.reliability', 'LEGACY')
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/PmDashboardApiTest.php`
Expected: FAIL — `data.milestone_progress_meta` key does not exist.

- [ ] **Step 3: Implement `computeMilestoneProgressMeta()` and wire it in**

Add `'milestone_progress_meta' => $this->computeMilestoneProgressMeta($projectId)->toArray(),` immediately after the `'milestone_progress' => ...` line in `getProjectProgress()`'s response array.

Add this new private method directly after `computeMilestoneProgress()`:

```php
    private function computeMilestoneProgressMeta(string $projectId): MetricResult
    {
        $label = 'Tỷ lệ hoàn thành mốc tiến độ';

        return MetricGuard::wrap(
            'milestone_progress',
            ['project_id' => $projectId, 'tenant_id' => (string) Auth::user()?->tenant_id],
            $label,
            function () use ($projectId, $label) {
                $total = ProjectMilestone::where('project_id', $projectId)->count();

                if ($total === 0) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NO_DATA,
                        reliability: Reliability::LEGACY,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Dự án chưa có mốc tiến độ (Milestone) nào được tạo. Nguồn dữ liệu này không còn kênh cập nhật chính thức.',
                    );
                }

                $completed = ProjectMilestone::where('project_id', $projectId)
                    ->where('status', ProjectMilestone::STATUS_COMPLETED)
                    ->count();

                $value = round(($completed / $total) * 100, 2);
                $asOf = ProjectMilestone::where('project_id', $projectId)->max('updated_at');

                return new MetricResult(
                    value: $value,
                    availability: Availability::AVAILABLE,
                    reliability: Reliability::LEGACY,
                    freshness: Freshness::UNKNOWN,
                    asOf: $asOf ? Carbon::parse($asOf) : null,
                    label: $label,
                    explanation: 'Dữ liệu lịch sử — không còn kênh cập nhật chính thức.',
                );
            },
        );
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/PmDashboardApiTest.php`
Expected: PASS, all tests green.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/PmDashboardController.php tests/Feature/Api/PmDashboardApiTest.php
git commit -m "feat(dashboard): add milestone_progress_meta (always LEGACY reliability)"
```

---

## Task 6: `budget_progress_meta` on `PmDashboardController`

**Files:**
- Modify: `app/Http/Controllers/Api/PmDashboardController.php`
- Test: `tests/Feature/Api/PmDashboardApiTest.php`

**Interfaces:**
- Consumes: same as Task 4.
- Produces: JSON field `data.budget_progress_meta`.

Spec reference: §4 (budget NOT_APPLICABLE branch).

**Deviation note (must read before implementing):** the spec (§4) describes the NOT_APPLICABLE trigger as "budget_total là null". The actual schema does not support this: `database/migrations/2025_09_15_041906_create_projects_table.php:26` declares `$table->decimal('budget_total', 15, 2)->default(0)` (NOT NULL, default 0), and `Project::getBudgetTotalAttribute()` (`app/Models/Project.php:95`) coalesces to `0.0` even if the raw attribute were somehow absent. `budget_total` can never actually be `null` at runtime. This plan implements the spec's clearly-stated *intent* — "dự án không dùng budget tracking" is explicitly listed as the NOT_APPLICABLE example in spec §1 — using the only observable signal available in this schema: `budget_total <= 0`. Flag this substitution in the PR description when this task is reviewed.

- [ ] **Step 1: Write the failing tests**

Add after the Task 5 test:

```php
    public function test_budget_progress_meta_is_not_applicable_when_no_budget_entered(): void
    {
        $project = $this->createAssignedProject('No budget project', Project::STATUS_ACTIVE);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.budget_progress', ['total_budget' => 0.0, 'spent_amount' => 0.0, 'remaining_amount' => 0.0, 'percentage_spent' => 0])
            ->assertJsonPath('data.budget_progress_meta.value', null)
            ->assertJsonPath('data.budget_progress_meta.availability', 'NOT_APPLICABLE')
            ->assertJsonPath('data.budget_progress_meta.reliability', 'RELIABLE');
    }
```

Then edit the existing big happy-path test, adding these lines immediately after `->assertJsonPath('data.budget_progress.percentage_spent', 25)`:

```php
            ->assertJsonPath('data.budget_progress_meta.value', 25)
            ->assertJsonPath('data.budget_progress_meta.availability', 'AVAILABLE')
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/PmDashboardApiTest.php`
Expected: FAIL — `data.budget_progress_meta` key does not exist.

- [ ] **Step 3: Implement `computeBudgetProgressMeta()` and wire it in**

Add `'budget_progress_meta' => $this->computeBudgetProgressMeta($project)->toArray(),` immediately after the `'budget_progress' => ...` line.

Add this new private method directly after `computeBudgetProgress()`:

```php
    private function computeBudgetProgressMeta(Project $project): MetricResult
    {
        $label = 'Tỷ lệ ngân sách đã chi';

        return MetricGuard::wrap(
            'budget_progress',
            ['project_id' => (string) $project->id, 'tenant_id' => (string) Auth::user()?->tenant_id],
            $label,
            function () use ($project, $label) {
                $total = (float) ($project->budget_total ?? 0);

                if ($total <= 0) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NOT_APPLICABLE,
                        reliability: Reliability::RELIABLE,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Dự án chưa nhập ngân sách.',
                    );
                }

                $spent = (float) ($project->budget_actual ?? 0);
                $value = round(($spent / $total) * 100, 2);

                return new MetricResult(
                    value: $value,
                    availability: Availability::AVAILABLE,
                    reliability: Reliability::RELIABLE,
                    freshness: Freshness::UNKNOWN,
                    asOf: $project->updated_at,
                    label: $label,
                    explanation: null,
                );
            },
        );
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/PmDashboardApiTest.php`
Expected: PASS, all tests green.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/PmDashboardController.php tests/Feature/Api/PmDashboardApiTest.php
git commit -m "feat(dashboard): add budget_progress_meta (NOT_APPLICABLE when budget_total<=0)"
```

---

## Task 7: `timeline_progress_meta` on `PmDashboardController` (label rename)

**Files:**
- Modify: `app/Http/Controllers/Api/PmDashboardController.php`
- Test: `tests/Feature/Api/PmDashboardApiTest.php`

**Interfaces:**
- Consumes: same as Task 4.
- Produces: JSON field `data.timeline_progress_meta`, label **"Tỷ lệ thời gian kế hoạch đã trôi qua"** (not "tiến độ" — spec §5 explicitly forbids calling this "progress").

Spec reference: §5 (`timeline_progress` semantics correction — `percentage_elapsed` returns `0`, not `null`, when dates are missing in the legacy code, so the meta path uses `NOT_APPLICABLE`, not a null-safe passthrough).

- [ ] **Step 1: Write the failing tests**

Add after the Task 6 test:

```php
    public function test_timeline_progress_meta_is_not_applicable_when_dates_missing(): void
    {
        $project = $this->createAssignedProject('No dates project', Project::STATUS_ACTIVE);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.timeline_progress.percentage_elapsed', 0)
            ->assertJsonPath('data.timeline_progress_meta.value', null)
            ->assertJsonPath('data.timeline_progress_meta.availability', 'NOT_APPLICABLE')
            ->assertJsonPath('data.timeline_progress_meta.reliability', 'RELIABLE')
            ->assertJsonPath('data.timeline_progress_meta.label', 'Tỷ lệ thời gian kế hoạch đã trôi qua')
            ->assertJsonPath('data.timeline_progress_meta.as_of', null);
    }
```

Then edit the existing big happy-path test, adding these lines immediately after `->assertJsonPath('data.timeline_progress.percentage_elapsed', 50);` (note: this is the LAST assertion in the chain, ending in `;` — change it to end with a comma and append the new lines before the closing `;`):

```php
            ->assertJsonPath('data.timeline_progress.percentage_elapsed', 50)
            ->assertJsonPath('data.timeline_progress_meta.value', 50)
            ->assertJsonPath('data.timeline_progress_meta.availability', 'AVAILABLE')
            ->assertJsonPath('data.timeline_progress_meta.label', 'Tỷ lệ thời gian kế hoạch đã trôi qua');
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/PmDashboardApiTest.php`
Expected: FAIL — `data.timeline_progress_meta` key does not exist.

- [ ] **Step 3: Implement `computeTimelineProgressMeta()` and wire it in**

Add `'timeline_progress_meta' => $this->computeTimelineProgressMeta($project)->toArray(),` immediately after the `'timeline_progress' => ...` line.

Add this new private method directly after `computeTimelineProgress()`:

```php
    private function computeTimelineProgressMeta(Project $project): MetricResult
    {
        $label = 'Tỷ lệ thời gian kế hoạch đã trôi qua';

        return MetricGuard::wrap(
            'timeline_progress',
            ['project_id' => (string) $project->id, 'tenant_id' => (string) Auth::user()?->tenant_id],
            $label,
            function () use ($project, $label) {
                if (!$project->start_date || !$project->end_date) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NOT_APPLICABLE,
                        reliability: Reliability::RELIABLE,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Dự án chưa nhập đủ ngày bắt đầu/kết thúc kế hoạch.',
                    );
                }

                $start = $project->start_date;
                $end = $project->end_date;
                $now = now()->startOfDay();

                $totalDays = (int) $start->diffInDays($end);
                $elapsedDays = (int) $start->diffInDays($now);
                $value = $totalDays > 0 ? round(min(($elapsedDays / $totalDays) * 100, 100), 2) : 0.0;

                return new MetricResult(
                    value: $value,
                    availability: Availability::AVAILABLE,
                    reliability: Reliability::RELIABLE,
                    freshness: Freshness::UNKNOWN,
                    asOf: null,
                    label: $label,
                    explanation: null,
                );
            },
        );
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/PmDashboardApiTest.php`
Expected: PASS, all tests green (including the original happy-path test's untouched `data.timeline_progress.percentage_elapsed` = 50 assertion).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/PmDashboardController.php tests/Feature/Api/PmDashboardApiTest.php
git commit -m "feat(dashboard): add timeline_progress_meta, rename label away from 'progress'"
```

---

## Task 8: Portal `outstandingBalance` → `MetricResult` + label change

**Files:**
- Modify: `app/Http/Controllers/Web/Portal/PortalDashboardController.php`
- Modify: `resources/views/portal/dashboard.blade.php`
- Test: `tests/Feature/Portal/PortalDashboardTest.php`

**Interfaces:**
- Consumes: `App\Support\Dashboard\{Availability, Reliability, Freshness, MetricResult, MetricGuard}` (Tasks 1–3).
- Produces: view variable `outstandingBalanceMetric` (a `MetricResult`) passed to `portal.dashboard` alongside the existing `outstandingBalance` float (kept for internal use only — per spec §12.2 this view has zero JSON/AJAX consumers, so unlike PM Dashboard there is no need to keep a client-facing legacy field name, but the underlying `$outstandingBalance` PHP variable inside the controller is still computed the same way and is not deleted, only supplemented).

Spec reference: §7.1 (Metric A — "Giá trị theo lịch chưa ghi nhận thanh toán"), §12.2 (Portal confirmed pure Blade, safe to change view directly).

- [ ] **Step 1: Write the failing test**

Open `tests/Feature/Portal/PortalDashboardTest.php`, find the existing test method (the one asserting dashboard content — read the file first to find its exact assertion style, e.g. `$response->assertSee(...)`), and add a new test method at the end of the class, before the final closing `}`:

```php
    public function test_dashboard_shows_scheduled_unpaid_label_and_explanation_not_confirmed_debt_wording(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-dash-2']);
        $staffUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang label test',
            'email' => 'label-test@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Du an label test',
            'code' => 'PRJ-LABEL1',
            'status' => 'active',
        ]);

        Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi label test',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'converted_project_id' => (string) $project->id,
            'sales_owner_id' => (string) $staffUser->id,
            'created_by' => (string) $staffUser->id,
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-LABEL1',
            'total_value' => 100000000,
            'currency' => 'VND',
            'status' => 'active',
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Dot 1',
            'amount' => 30000000,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => ContractPayment::STATUS_PLANNED,
        ]);

        $response = $this->actingAs($account, 'client')->get('/portal/zena-dash-2/dashboard');

        $response->assertOk();
        $response->assertSee('Giá trị theo lịch chưa ghi nhận thanh toán');
        $response->assertDontSee('Số dư còn lại');
        $response->assertSee('chưa ghi nhận thanh toán từng phần', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Portal/PortalDashboardTest.php --filter test_dashboard_shows_scheduled_unpaid_label_and_explanation_not_confirmed_debt_wording`
Expected: FAIL — page still shows old label "Số dư còn lại" instead of the new label, and does not contain the explanation text.

- [ ] **Step 3: Implement the controller change**

In `app/Http/Controllers/Web/Portal/PortalDashboardController.php`, add these imports after `use App\Models\Tenant;`:

```php
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\MetricGuard;
use App\Support\Dashboard\MetricResult;
use App\Support\Dashboard\Reliability;
use Carbon\Carbon;
```

Replace the existing `$outstandingBalance` computation:

```php
        $outstandingBalance = (float) ContractPayment::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->where('status', '!=', ContractPayment::STATUS_PAID)
            ->sum('amount');
```

with:

```php
        $outstandingBalance = (float) ContractPayment::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->where('status', '!=', ContractPayment::STATUS_PAID)
            ->sum('amount');

        $outstandingBalanceMetric = $this->computeOutstandingBalanceMetric($tenant->id, $contracts->pluck('id')->all(), $outstandingBalance);
```

Add `'outstandingBalanceMetric' => $outstandingBalanceMetric,` to the `view('portal.dashboard', [...])` array, immediately after `'outstandingBalance' => $outstandingBalance,`.

Add this new private method at the end of the class, before the final closing `}`:

```php
    private function computeOutstandingBalanceMetric(string $tenantId, array $contractIds, float $alreadyComputedSum): MetricResult
    {
        $label = 'Giá trị theo lịch chưa ghi nhận thanh toán';

        return MetricGuard::wrap(
            'outstandingBalance',
            ['tenant_id' => $tenantId],
            $label,
            function () use ($tenantId, $contractIds, $alreadyComputedSum, $label) {
                $scheduleCount = ContractPayment::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('contract_id', $contractIds)
                    ->count();

                if ($scheduleCount === 0) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NO_DATA,
                        reliability: Reliability::LIMITED,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Chưa có lịch thanh toán nào được thiết lập cho dự án này.',
                    );
                }

                $asOf = ContractPayment::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('contract_id', $contractIds)
                    ->max('updated_at');

                return new MetricResult(
                    value: $alreadyComputedSum,
                    availability: Availability::AVAILABLE,
                    reliability: Reliability::LIMITED,
                    freshness: Freshness::UNKNOWN,
                    asOf: $asOf ? Carbon::parse($asOf) : null,
                    label: $label,
                    explanation: "Số liệu này cộng tất cả các khoản thanh toán theo lịch hợp đồng chưa được đánh dấu 'đã thanh toán', kể cả các khoản chưa tới hạn. Hệ thống hiện chưa ghi nhận thanh toán từng phần, nên số liệu này không phải công nợ thực tế đã xác nhận.",
                );
            },
        );
    }
```

- [ ] **Step 4: Update the Blade view**

In `resources/views/portal/dashboard.blade.php`, replace line 115:

```blade
            <x-ui.field-value label="Số dư còn lại" :value="number_format($outstandingBalance, 0, ',', '.') . '₫'" />
```

with:

```blade
            <x-ui.field-value
                :label="$outstandingBalanceMetric->label"
                :value="$outstandingBalanceMetric->value !== null ? number_format($outstandingBalanceMetric->value, 0, ',', '.') . '₫' : null"
            />
            @if ($outstandingBalanceMetric->explanation)
                <p class="mt-1 text-xs text-slate-500">{{ $outstandingBalanceMetric->explanation }}</p>
            @endif
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/Portal/PortalDashboardTest.php`
Expected: PASS, all tests in the file green (including the pre-existing test — `outstandingBalance` PHP variable and its numeric value are unchanged, only the visible label/explanation text changed).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/Portal/PortalDashboardController.php resources/views/portal/dashboard.blade.php tests/Feature/Portal/PortalDashboardTest.php
git commit -m "feat(dashboard): relabel Portal outstanding balance as scheduled-unpaid, add MetricResult"
```

---

## Task 9: CRM `outstandingDebt.total` → Metric A `MetricResult` + label change

**Files:**
- Modify: `app/Http/Controllers/Web/CrmReportController.php`
- Modify: `resources/views/crm/report.blade.php`
- Test: `tests/Feature/Zena/CrmReportPageTest.php`

**Interfaces:**
- Consumes: `App\Support\Dashboard\{Availability, Reliability, Freshness, MetricResult, MetricGuard}` (Tasks 1–3), `App\Services\BusinessKpiService::outstandingDebt(string $tenantId): array` (existing, unmodified).
- Produces: view variable `outstandingDebtTotalMetric` passed to `crm.report`.

Spec reference: §7.1 (Metric A, same label/explanation as Portal), §12.2 (CRM confirmed pure Blade).

- [ ] **Step 1: Write the failing test**

Add a new test method to `tests/Feature/Zena/CrmReportPageTest.php`, after the existing `test_report_page_renders_real_kpi_data` method:

```php
    public function test_report_page_relabels_outstanding_debt_total_as_scheduled_unpaid(): void
    {
        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang label test',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Du an label test',
            'code' => 'PRJ-CRMLABEL',
            'status' => 'active',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-CRMLABEL',
            'total_value' => 50000000,
            'currency' => 'VND',
            'status' => 'active',
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Dot 1',
            'amount' => 20000000,
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => ContractPayment::STATUS_PLANNED,
        ]);

        $this->actingAs($this->viewer)
            ->get(route('crm.report'))
            ->assertOk()
            ->assertSee('Giá trị theo lịch chưa ghi nhận thanh toán')
            ->assertDontSee('Tổng công nợ');
    }

    public function test_report_page_shows_no_data_when_tenant_has_no_payment_schedule(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('crm.report'))
            ->assertOk()
            ->assertSee('Chưa có lịch thanh toán');
    }
```

(Route name `crm.report` — verify against `routes/web.php` before running; if the route is unnamed, replace `route('crm.report')` with the literal path `'/operator/crm/reports'` used elsewhere in this file's existing test.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Zena/CrmReportPageTest.php`
Expected: FAIL — page still shows "Tổng công nợ" and has no "Chưa có lịch thanh toán" text.

- [ ] **Step 3: Implement the controller change**

In `app/Http/Controllers/Web/CrmReportController.php`, replace the whole file with:

```php
<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContractPayment;
use App\Services\BusinessKpiService;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\MetricGuard;
use App\Support\Dashboard\MetricResult;
use App\Support\Dashboard\Reliability;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class CrmReportController extends Controller
{
    public function index(BusinessKpiService $kpiService): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;
        $outstandingDebt = $kpiService->outstandingDebt($tenantId);

        return view('crm.report', [
            'monthlyRevenue' => $kpiService->monthlyRevenue($tenantId),
            'pipelineByStage' => $kpiService->pipelineByStage($tenantId),
            'outstandingDebt' => $outstandingDebt,
            'outstandingDebtTotalMetric' => $this->computeOutstandingDebtTotalMetric($tenantId, $outstandingDebt['total']),
            'outstandingDebtOverdueMetric' => $this->computeOutstandingDebtOverdueMetric($tenantId, $outstandingDebt),
            'salesWinRate' => $kpiService->salesWinRate($tenantId),
            'serviceCategoryPerformance' => $kpiService->serviceCategoryPerformance($tenantId),
        ]);
    }

    private function hasAnyPaymentSchedule(string $tenantId): bool
    {
        return ContractPayment::query()->where('tenant_id', $tenantId)->exists();
    }

    private function computeOutstandingDebtTotalMetric(string $tenantId, float $total): MetricResult
    {
        $label = 'Giá trị theo lịch chưa ghi nhận thanh toán';

        return MetricGuard::wrap(
            'outstandingDebt.total',
            ['tenant_id' => $tenantId],
            $label,
            function () use ($tenantId, $total, $label) {
                if (!$this->hasAnyPaymentSchedule($tenantId)) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NO_DATA,
                        reliability: Reliability::LIMITED,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Chưa có lịch thanh toán nào được thiết lập.',
                    );
                }

                $asOf = ContractPayment::query()->where('tenant_id', $tenantId)->max('updated_at');

                return new MetricResult(
                    value: $total,
                    availability: Availability::AVAILABLE,
                    reliability: Reliability::LIMITED,
                    freshness: Freshness::UNKNOWN,
                    asOf: $asOf ? Carbon::parse($asOf) : null,
                    label: $label,
                    explanation: "Số liệu này cộng tất cả các khoản thanh toán theo lịch hợp đồng chưa được đánh dấu 'đã thanh toán', kể cả các khoản chưa tới hạn. Hệ thống hiện chưa ghi nhận thanh toán từng phần, nên số liệu này không phải công nợ thực tế đã xác nhận.",
                );
            },
        );
    }

    /**
     * @param array{total: float, overdue_total: float, overdue_count: int, aging: array{not_due: float, due_1_30: float, due_31_60: float, due_61_90: float, due_over_90: float}} $outstandingDebt
     */
    private function computeOutstandingDebtOverdueMetric(string $tenantId, array $outstandingDebt): MetricResult
    {
        $label = 'Giá trị đã quá hạn theo lịch, chưa ghi nhận thanh toán';

        return MetricGuard::wrap(
            'outstandingDebt.overdue_total',
            ['tenant_id' => $tenantId],
            $label,
            function () use ($tenantId, $outstandingDebt, $label) {
                if (!$this->hasAnyPaymentSchedule($tenantId)) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NO_DATA,
                        reliability: Reliability::LIMITED,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Chưa có lịch thanh toán nào được thiết lập.',
                    );
                }

                return new MetricResult(
                    value: $outstandingDebt['overdue_total'],
                    availability: Availability::AVAILABLE,
                    reliability: Reliability::LIMITED,
                    freshness: Freshness::UNKNOWN,
                    asOf: Carbon::now(),
                    label: $label,
                    explanation: 'Số liệu này chỉ tính các khoản đã tới hoặc quá hạn thanh toán theo lịch hợp đồng (dựa trên ngày đến hạn, không dựa vào nhãn trạng thái thủ công), chưa được đánh dấu \'đã thanh toán\'. Chưa phản ánh các khoản đã thu một phần.',
                );
            },
        );
    }
}
```

Note: Task 10 also touches `computeOutstandingDebtOverdueMetric()` — it is written here already (both metrics share the "has any schedule" check) so that Task 9 and Task 10 do not conflict when editing the same file; Task 10 only adds the aging-bucket test coverage and view wiring, no further controller changes.

- [ ] **Step 4: Update the Blade view (Metric A portion only — aging portion is Task 10)**

In `resources/views/crm/report.blade.php`, replace line 34:

```blade
                <x-ui.field-value label="Tổng công nợ" :value="number_format($outstandingDebt['total'], 0, ',', '.') . '₫'" />
```

with:

```blade
                <x-ui.field-value
                    :label="$outstandingDebtTotalMetric->label"
                    :value="$outstandingDebtTotalMetric->value !== null ? number_format($outstandingDebtTotalMetric->value, 0, ',', '.') . '₫' : null"
                />
                @if ($outstandingDebtTotalMetric->explanation)
                    <p class="col-span-full text-xs text-slate-500">{{ $outstandingDebtTotalMetric->explanation }}</p>
                @endif
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Zena/CrmReportPageTest.php`
Expected: PASS for the two new tests and the pre-existing `test_report_page_renders_real_kpi_data` test (which does not assert on the old "Tổng công nợ" text, per the file excerpt read earlier — verify this by reading the full existing test before running; if it does assert the old label, update that assertion to the new label as part of this step).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/CrmReportController.php resources/views/crm/report.blade.php tests/Feature/Zena/CrmReportPageTest.php
git commit -m "feat(dashboard): relabel CRM outstanding debt total as scheduled-unpaid, add MetricResult"
```

---

## Task 10: CRM `outstandingDebt` aging buckets → Metric B `MetricResult` + due_date-based test coverage

**Files:**
- Modify: `resources/views/crm/report.blade.php`
- Test: `tests/Feature/Zena/CrmReportPageTest.php`

**Interfaces:**
- Consumes: `computeOutstandingDebtOverdueMetric()` (already implemented in Task 9's controller rewrite), `App\Services\BusinessKpiService::outstandingDebt()` (existing, unmodified — its `due_date`-based aging logic at `app/Services/BusinessKpiService.php:70,81` is reused as-is, not reimplemented).

Spec reference: §7.2 (Metric B, aging buckets, due_date-wins-over-status determination), Test Matrix cases 14–15.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Zena/CrmReportPageTest.php`, after the tests added in Task 9:

```php
    public function test_aging_not_due_bucket_excludes_future_dated_payment_from_overdue_total(): void
    {
        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang aging test',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Du an aging test',
            'code' => 'PRJ-AGING1',
            'status' => 'active',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-AGING1',
            'total_value' => 10000000,
            'currency' => 'VND',
            'status' => 'active',
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Ky tuong lai',
            'amount' => 10000000,
            'due_date' => now()->addDays(45)->toDateString(),
            'status' => ContractPayment::STATUS_PLANNED,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('crm.report'));

        $response->assertOk();
        $response->assertSee('Giá trị đã quá hạn theo lịch, chưa ghi nhận thanh toán');
        // overdue_total must be 0 (the payment is not_due), rendered as "0₫"
        $response->assertSeeInOrder(['Giá trị đã quá hạn theo lịch, chưa ghi nhận thanh toán', '0₫']);
    }

    public function test_aging_due_date_wins_over_stale_status_field(): void
    {
        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang aging test 2',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Du an aging test 2',
            'code' => 'PRJ-AGING2',
            'status' => 'active',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-AGING2',
            'total_value' => 5000000,
            'currency' => 'VND',
            'status' => 'active',
        ]);

        // status is still "planned" (never manually flipped to "overdue"), but due_date is 10 days in the past —
        // BusinessKpiService::outstandingDebt() must still count this as overdue because it compares due_date, not status.
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Ky qua han nhung status chua cap nhat',
            'amount' => 5000000,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => ContractPayment::STATUS_PLANNED,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('crm.report'));

        $response->assertOk();
        $response->assertSeeInOrder(['Giá trị đã quá hạn theo lịch, chưa ghi nhận thanh toán', '5.000.000₫']);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Zena/CrmReportPageTest.php --filter test_aging`
Expected: FAIL — the view does not yet render the Metric B label/value at all (still shows old "Quá hạn" label from the unmodified aging portion of the view).

- [ ] **Step 3: Update the Blade view — aging section**

In `resources/views/crm/report.blade.php`, replace lines 35–43 (the `Quá hạn` / `Số khoản quá hạn` / aging bucket rows) with:

```blade
                <x-ui.field-value
                    :label="$outstandingDebtOverdueMetric->label"
                    :value="$outstandingDebtOverdueMetric->value !== null ? number_format($outstandingDebtOverdueMetric->value, 0, ',', '.') . '₫' : null"
                />
                @if ($outstandingDebtOverdueMetric->explanation)
                    <p class="col-span-full text-xs text-slate-500">{{ $outstandingDebtOverdueMetric->explanation }}</p>
                @endif
                <x-ui.field-value label="Số khoản quá hạn" :value="(string) $outstandingDebt['overdue_count']" />
                <x-ui.field-value label="Chưa đến hạn" :value="number_format($outstandingDebt['aging']['not_due'], 0, ',', '.') . '₫'" />
                <x-ui.field-value label="Quá hạn 1-30 ngày" :value="number_format($outstandingDebt['aging']['due_1_30'], 0, ',', '.') . '₫'" />
                <x-ui.field-value label="Quá hạn 31-60 ngày" :value="number_format($outstandingDebt['aging']['due_31_60'], 0, ',', '.') . '₫'" />
                <x-ui.field-value label="Quá hạn 61-90 ngày" :value="number_format($outstandingDebt['aging']['due_61_90'], 0, ',', '.') . '₫'" />
                <x-ui.field-value label="Quá hạn trên 90 ngày" :value="number_format($outstandingDebt['aging']['due_over_90'], 0, ',', '.') . '₫'" />
```

(The 5 raw aging-bucket rows and `overdue_count` keep reading directly from `$outstandingDebt['aging']`/`$outstandingDebt['overdue_count']` — spec §7.2 only requires the `overdue_total` headline number to carry trust metadata, not each individual bucket; the buckets are already unambiguous since they only ever appear alongside the Metric B trust label.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Zena/CrmReportPageTest.php`
Expected: PASS, all tests in the file green.

- [ ] **Step 5: Commit**

```bash
git add resources/views/crm/report.blade.php tests/Feature/Zena/CrmReportPageTest.php
git commit -m "feat(dashboard): wire overdue Metric B into CRM report view, due_date-based aging tests"
```

---

## Task 11: Full regression pass

**Files:** none created/modified — verification only.

**Interfaces:** none.

- [ ] **Step 1: Run every touched test file together**

Run: `./vendor/bin/phpunit tests/Unit/Support/Dashboard tests/Feature/Api/PmDashboardApiTest.php tests/Feature/Portal/PortalDashboardTest.php tests/Feature/Zena/CrmReportPageTest.php`
Expected: PASS, 0 failures.

- [ ] **Step 2: Run the full test suite to check for unrelated regressions**

Run: `./vendor/bin/phpunit`
Expected: same pass/fail count as the baseline before this plan started (no new failures outside the files touched in Tasks 1–10). If any pre-existing unrelated failure is present, confirm via `git stash` + re-run that it predates this plan before proceeding — do not attempt to fix unrelated failures as part of this plan.

- [ ] **Step 3: Manually verify the legacy-field invariant end-to-end**

Run: `php artisan tinker --execute="dd(true);"` is not needed — instead, start the dev server and manually confirm via `curl` or the browser that `GET api/zena/pm/progress?project_id=<real id>` still returns `overall_progress`, `milestone_progress`, `budget_progress`, `timeline_progress` as plain numbers/objects exactly as before (compare against a `git show <pre-plan-commit>:app/Http/Controllers/Api/PmDashboardController.php` diff if any doubt remains), confirming the additive-only guarantee from spec §3.2 holds in a real running request, not just in tests.

- [ ] **Step 4: Commit (only if Step 2/3 required any follow-up fix; otherwise skip — nothing to commit)**

```bash
git status
```

If clean, no commit needed — Task 11 is a verification-only task.

---

## Self-Review

**Spec coverage:**
- §1 (3-dimension model) → Task 1.
- §2 (`MetricResult`) → Task 2.
- §3 (API compatibility, legacy fields untouched, `*_meta` sibling, equality invariant) → Tasks 4–7 (each adds the equality assertion into the existing happy-path test).
- §4 (Progress semantics: zero-denominator vs real-zero vs error) → Task 4 (overall), reused pattern in Task 5 (milestone).
- §5 (`timeline_progress` rename + `NOT_APPLICABLE` correction) → Task 7.
- §6 (Milestone `LEGACY` in both branches) → Task 5.
- §7.1/§7.2 (Payment Metric A/B, aging, due_date-wins-over-status) → Tasks 8, 9, 10.
- §8 (ERROR semantics, correlation ID, partial-success, no request-level leakage) → Task 3 (`MetricGuard`), reused everywhere.
- §9 (Test Matrix 16 cases) → covered: real zero (Task 4 happy path), zero denominator (Task 4), no data (Tasks 4/5/8/9), not applicable (Tasks 6/7), available+limited (Task 8/9), available+legacy (Task 5), no-data+legacy (Task 5), freshness unknown (implicit — every `MetricResult` in every task hardcodes `Freshness::UNKNOWN`, no test needed since it's structurally impossible to produce another value in Phase 1 code), query error+log (Task 3 unit test), API compatibility (Tasks 4–7 legacy-unchanged assertions), mixed projects (not applicable — Phase 1 endpoints are per-project/per-tenant, not multi-project aggregation; no such endpoint exists in scope), tenant isolation (already covered by existing `TenantScope`/`tenant_id` filters reused unmodified in every new query), aging not_due/due_date-wins (Task 10), accessibility (out of scope for this backend-and-Blade-label plan — flagged below as a gap, not silently dropped).
- §10 (Evidence follow-up tickets) → no code task; both are separate tickets explicitly marked out of scope in Global Constraints.
- §11 (Rollout order) → Task ordering follows spec exactly: PM Dashboard widgets (4-7) before Portal (8) before CRM (9-10), consumer inventory already closed per spec §12.
- §12 (Consumer inventory) → informs Task 4's "keep legacy untouched" and Task 8/9's "no `_meta` sibling needed for Blade" design choices directly.

**Gap acknowledged, not silently dropped:** Test Matrix case 16 (accessibility — badges need text/icon/aria-label, not just color) has no task here because Phase 1 as scoped in this plan only wires `MetricResult` data through; it does not build the actual trust badge UI component (the spec's Rollout §11 describes label/explanation text changes, not a new badge component). If a badge component is added later, its accessibility requirements apply then — flag this to the operator before closing Phase 1 as "done" if a visual badge is expected now rather than in a follow-up.

**Placeholder scan:** no "TBD"/"handle edge cases"/"similar to Task N" found — every step has complete, runnable code specific to its own file and line numbers.

**Type consistency check:** `MetricResult` constructor signature (Task 2) — `mixed $value, Availability, Reliability, Freshness, ?Carbon $asOf, string $label, ?string $explanation` — used identically across Tasks 4–10 (every `new MetricResult(...)` call uses named arguments matching this exact signature). `MetricGuard::wrap(string $widget, array $logContext, string $label, \Closure $compute): MetricResult` (Task 3) — called identically in Tasks 4–9 with the same 4-argument shape. No renamed methods/fields found between the task that defines them and the tasks that consume them.

---

Plan complete and saved to `docs/superpowers/plans/2026-07-25-dashboard-data-trust-guardrails-implementation.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
