# Phase 9 (AI Use Case 3) — Document Checklist Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a read-only "Checklist tài liệu" report to the Project detail page that flags which document types, required by the project's applied WorkTemplate steps, have not yet been uploaded. This is a deterministic PHP comparison — it makes no Anthropic API call at all.

**Architecture:** A new nullable JSON column `required_document_types` on `work_template_steps` lets template authors declare, per step, which `Document.document_type` values that step expects (via the existing `Api\WorkTemplateController` step-definition payload — no new UI for template authoring). A new `App\Services\DocumentChecklistService::buildReport(Project $project): array` resolves the project's `scope_type = 'project'` `WorkInstance` rows, walks their `WorkInstanceStep`s back to the originating `WorkTemplateStep` via the live `work_template_step_id` foreign key, and diffs each step's `required_document_types` against the set of `document_type` values already present on the project's `Document` rows (project-wide matching, not per-step). The report is rendered as a new card on the existing `projects.show` Blade view, conditionally shown only to users with the `work.view` permission (checked in the Blade, not via route middleware, since the existing `app.projects.show` route has no `rbac:*` gate today and adding one would be a scope-creeping behavior change).

**Tech Stack:** Laravel 12, existing `WorkTemplate`/`WorkTemplateStep`/`WorkInstance`/`WorkInstanceStep`/`Document` Eloquent models, Blade.

## Global Constraints

- **No AI/LLM call anywhere in this feature.** This is pure PHP set-comparison logic — do not add any `AiAssistService` usage, any Anthropic API call, or any new AI-related config.
- **Requirement declaration is per-`WorkTemplateStep`**, stored as a nullable JSON array of values drawn from `Document::VALID_DOCUMENT_TYPES` (a new constant: `drawing`, `specification`, `contract`, `report`, `photo`, `other` — the existing enum already validated by `Api\DocumentController`, just not yet extracted into a reusable constant).
- **Matching is project-wide, not step-specific.** A required document type counts as "present" if the Project has ANY `Document` row (regardless of which step, if any, it relates to) whose `document_type` matches — do not attempt to link `Document` rows to specific `WorkInstanceStep`s.
- **Only `scope_type = 'project'` `WorkInstance` rows are considered.** WorkInstances applied to individual `Component`s are out of scope for this report.
- **Read-only.** This feature must never write to `WorkInstanceStep`, `Document`, or create any task/notification — it only renders a report.
- **The `projects.show` page (`app.projects.show`, `Web\ProjectController::show()`) has no `rbac:*` middleware today** — do not add route-level middleware to gate it. The new card's visibility is decided inline in the Blade via `Auth::user()->hasPermission('work.view')`.
- **`declare(strict_types=1)`** at the top of every new/modified PHP file, matching the rest of the codebase.

---

### Task 1: `required_document_types` schema + `Document::VALID_DOCUMENT_TYPES` + template wiring

**Files:**
- Create: `database/migrations/2026_07_12_100000_add_required_document_types_to_work_template_steps_table.php`
- Modify: `app/Models/WorkTemplateStep.php` (`$fillable`, `$casts`)
- Modify: `app/Models/Document.php` (new `VALID_DOCUMENT_TYPES` constant)
- Modify: `app/Http/Controllers/Api/WorkTemplateController.php` (`store()`, `update()`, `syncStepsAndFields()`)
- Test: `tests/Feature/Api/WorkTemplateRequiredDocumentTypesTest.php` (new)

**Interfaces:**
- Consumes: nothing new from other tasks.
- Produces: `WorkTemplateStep.required_document_types` (nullable array, cast) and `App\Models\Document::VALID_DOCUMENT_TYPES` (a `list<string>` constant: `['drawing', 'specification', 'contract', 'report', 'photo', 'other']`) — both symbols Task 2 depends on.

- [ ] **Step 1: Write the migration**

Create `database/migrations/2026_07_12_100000_add_required_document_types_to_work_template_steps_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_template_steps', function (Blueprint $table) {
            $table->json('required_document_types')->nullable()->after('config_json');
        });
    }

    public function down(): void
    {
        Schema::table('work_template_steps', function (Blueprint $table) {
            $table->dropColumn('required_document_types');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `2026_07_12_100000_add_required_document_types_to_work_template_steps_table ... DONE`

- [ ] **Step 3: Add `required_document_types` to `WorkTemplateStep`**

In `app/Models/WorkTemplateStep.php`, add `'required_document_types'` to the `$fillable` array (after `'config_json'`):

```php
    protected $fillable = [
        'tenant_id',
        'work_template_version_id',
        'step_key',
        'name',
        'type',
        'step_order',
        'depends_on',
        'assignee_rule_json',
        'sla_hours',
        'config_json',
        'required_document_types',
    ];
```

And add it to the `$casts` array:

```php
    protected $casts = [
        'depends_on' => 'array',
        'assignee_rule_json' => 'array',
        'config_json' => 'array',
        'required_document_types' => 'array',
    ];
```

- [ ] **Step 4: Add the `VALID_DOCUMENT_TYPES` constant to `Document`**

In `app/Models/Document.php`, add these constants immediately after the existing `public const VISIBILITY_CLIENT = 'client';` line (and before `protected $fillable = [`):

```php
    public const DOCUMENT_TYPE_DRAWING = 'drawing';
    public const DOCUMENT_TYPE_SPECIFICATION = 'specification';
    public const DOCUMENT_TYPE_CONTRACT = 'contract';
    public const DOCUMENT_TYPE_REPORT = 'report';
    public const DOCUMENT_TYPE_PHOTO = 'photo';
    public const DOCUMENT_TYPE_OTHER = 'other';

    public const VALID_DOCUMENT_TYPES = [
        self::DOCUMENT_TYPE_DRAWING,
        self::DOCUMENT_TYPE_SPECIFICATION,
        self::DOCUMENT_TYPE_CONTRACT,
        self::DOCUMENT_TYPE_REPORT,
        self::DOCUMENT_TYPE_PHOTO,
        self::DOCUMENT_TYPE_OTHER,
    ];
```

This matches the enum already enforced by `Api\DocumentController`'s existing `'document_type' => 'required|in:drawing,specification,contract,report,photo,other'` validation string — do not change that controller's validation in this task; it is out of scope (the spec explicitly does not fix the pre-existing `document_type` validation inconsistency across upload paths).

- [ ] **Step 5: Write the failing feature test**

Create `tests/Feature/Api/WorkTemplateRequiredDocumentTypesTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class WorkTemplateRequiredDocumentTypesTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant, [], ['admin'], ['template.view', 'template.edit_draft']);
    }

    private function authHeaders(): array
    {
        $token = $this->user->createToken('required-doc-types-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenant->id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    public function test_store_persists_required_document_types_on_step(): void
    {
        $response = $this->postJson(route('api.zena.work-templates.store'), [
            'code' => 'WT-DOC-CHECK-1',
            'name' => 'Template With Document Requirements',
            'status' => 'draft',
            'steps' => [
                [
                    'key' => 'issue-drawings',
                    'name' => 'Issue Drawings',
                    'type' => 'deliverable',
                    'order' => 1,
                    'required_document_types' => ['drawing', 'specification'],
                ],
            ],
        ], $this->authHeaders());

        $response->assertCreated();

        $step = WorkTemplateStep::query()->where('step_key', 'issue-drawings')->firstOrFail();
        $this->assertSame(['drawing', 'specification'], $step->required_document_types);
    }

    public function test_store_rejects_invalid_document_type(): void
    {
        $response = $this->postJson(route('api.zena.work-templates.store'), [
            'code' => 'WT-DOC-CHECK-2',
            'name' => 'Template With Bad Document Type',
            'status' => 'draft',
            'steps' => [
                [
                    'key' => 'issue-drawings',
                    'name' => 'Issue Drawings',
                    'type' => 'deliverable',
                    'order' => 1,
                    'required_document_types' => ['not_a_real_type'],
                ],
            ],
        ], $this->authHeaders());

        $response->assertStatus(422);
        $this->assertDatabaseMissing('work_templates', ['code' => 'WT-DOC-CHECK-2']);
    }

    public function test_store_allows_step_without_required_document_types(): void
    {
        $response = $this->postJson(route('api.zena.work-templates.store'), [
            'code' => 'WT-DOC-CHECK-3',
            'name' => 'Template Without Document Requirements',
            'status' => 'draft',
            'steps' => [
                [
                    'key' => 'plain-step',
                    'name' => 'Plain Step',
                    'type' => 'task',
                    'order' => 1,
                ],
            ],
        ], $this->authHeaders());

        $response->assertCreated();

        $step = WorkTemplateStep::query()->where('step_key', 'plain-step')->firstOrFail();
        $this->assertNull($step->required_document_types);
    }

    public function test_update_replaces_required_document_types(): void
    {
        $template = WorkTemplate::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'code' => 'WT-DOC-CHECK-4',
            'status' => 'draft',
            'created_by' => (string) $this->user->id,
            'updated_by' => (string) $this->user->id,
        ]);

        WorkTemplateVersion::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'work_template_id' => (string) $template->id,
            'semver' => 'draft-initial',
            'content_json' => ['steps' => [], 'approvals' => [], 'rules' => []],
            'is_immutable' => false,
            'created_by' => (string) $this->user->id,
            'updated_by' => (string) $this->user->id,
        ]);

        $response = $this->putJson(route('api.zena.work-templates.update', $template->id), [
            'steps' => [
                [
                    'key' => 'final-review',
                    'name' => 'Final Review',
                    'type' => 'approval',
                    'order' => 1,
                    'required_document_types' => ['report'],
                ],
            ],
        ], $this->authHeaders());

        $response->assertOk();

        $step = WorkTemplateStep::query()->where('step_key', 'final-review')->firstOrFail();
        $this->assertSame(['report'], $step->required_document_types);
    }
}
```

- [ ] **Step 6: Run the tests to verify they fail**

Run: `php artisan test --filter=WorkTemplateRequiredDocumentTypesTest`
Expected: FAIL — `test_store_persists_required_document_types_on_step` and `test_update_replaces_required_document_types` fail because the field isn't persisted yet (assertion on `$step->required_document_types` fails, value is `null`); `test_store_rejects_invalid_document_type` fails because no validation rule exists yet to reject it (the store succeeds instead of returning 422).

- [ ] **Step 7: Add validation rules to `store()` and `update()`**

In `app/Http/Controllers/Api/WorkTemplateController.php`, add `use App\Models\Document;` and `use Illuminate\Validation\Rule;` to the existing `use` block at the top of the file.

In `store()`'s `Validator::make()` call, add two rules immediately after `'steps.*.fields.*.visibility_rule' => 'nullable|array',`:

```php
            'steps.*.fields.*.visibility_rule' => 'nullable|array',
            'steps.*.required_document_types' => 'nullable|array',
            'steps.*.required_document_types.*' => ['string', Rule::in(Document::VALID_DOCUMENT_TYPES)],
```

In `update()`'s `Validator::make()` call, add the same two rules immediately after `'steps.*.fields' => 'nullable|array',`:

```php
            'steps.*.fields' => 'nullable|array',
            'steps.*.required_document_types' => 'nullable|array',
            'steps.*.required_document_types.*' => ['string', Rule::in(Document::VALID_DOCUMENT_TYPES)],
```

- [ ] **Step 8: Persist `required_document_types` in `syncStepsAndFields()`**

In the same file's `syncStepsAndFields()` method, add one line to the `WorkTemplateStep::create([...])` call:

```php
            $step = WorkTemplateStep::create([
                'tenant_id' => $version->tenant_id,
                'work_template_version_id' => $version->id,
                'step_key' => (string) ($stepData['key'] ?? ''),
                'name' => $stepData['name'] ?? null,
                'type' => (string) ($stepData['type'] ?? 'task'),
                'step_order' => (int) ($stepData['order'] ?? 1),
                'depends_on' => $stepData['depends_on'] ?? [],
                'assignee_rule_json' => $stepData['assignee_rule'] ?? null,
                'sla_hours' => isset($stepData['sla_hours']) ? (int) $stepData['sla_hours'] : null,
                'config_json' => $stepData['config'] ?? null,
                'required_document_types' => $stepData['required_document_types'] ?? null,
            ]);
```

- [ ] **Step 9: Run the tests to verify they pass**

Run: `php artisan test --filter=WorkTemplateRequiredDocumentTypesTest`
Expected: PASS (4/4)

- [ ] **Step 10: Run the pre-existing WorkTemplate test files to confirm nothing broke**

Run: `php artisan test --filter=WorkTemplateMvpApiTest`
Run: `php artisan test --filter=WorkTemplateBaselineSeederTest`
Expected: Both PASS with the same results as before this task (the added validation rules are `nullable`, so any existing test payload without `required_document_types` is unaffected).

- [ ] **Step 11: Commit**

```bash
git add database/migrations/2026_07_12_100000_add_required_document_types_to_work_template_steps_table.php app/Models/WorkTemplateStep.php app/Models/Document.php app/Http/Controllers/Api/WorkTemplateController.php tests/Feature/Api/WorkTemplateRequiredDocumentTypesTest.php
git commit -m "feat(work-templates): add required_document_types to WorkTemplateStep and Document::VALID_DOCUMENT_TYPES"
```

---

### Task 2: `DocumentChecklistService`

**Files:**
- Create: `app/Services/DocumentChecklistService.php`
- Test: `tests/Unit/DocumentChecklistServiceTest.php`

**Interfaces:**
- Consumes: `WorkTemplateStep.required_document_types` and `Document::VALID_DOCUMENT_TYPES` (Task 1).
- Produces: `App\Services\DocumentChecklistService::buildReport(Project $project): array` — returns a `list<array{step_id: string, step_name: string, required: list<string>, missing: list<string>}>`, one entry per `WorkInstanceStep` (across all `scope_type = 'project'` WorkInstances for the project) whose originating `WorkTemplateStep` has a non-empty `required_document_types`. Steps with no requirements are omitted entirely (nothing to report). This is the only symbol Task 3 depends on.

- [ ] **Step 1: Write the failing unit tests**

Create `tests/Unit/DocumentChecklistServiceTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Models\WorkTemplateStep;
use App\Services\DocumentChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentChecklistServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(): Project
    {
        $tenant = Tenant::factory()->create();

        return Project::factory()->create(['tenant_id' => (string) $tenant->id]);
    }

    public function test_flags_missing_required_document_type(): void
    {
        $project = $this->makeProject();

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'name' => 'Issue Drawings',
            'required_document_types' => ['drawing', 'specification'],
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'scope_type' => 'project',
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'work_instance_id' => (string) $instance->id,
            'work_template_step_id' => (string) $templateStep->id,
            'name' => 'Issue Drawings',
        ]);

        Document::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'document_type' => 'drawing',
        ]);

        $report = (new DocumentChecklistService())->buildReport($project);

        $this->assertCount(1, $report);
        $this->assertSame('Issue Drawings', $report[0]['step_name']);
        $this->assertSame(['drawing', 'specification'], $report[0]['required']);
        $this->assertSame(['specification'], $report[0]['missing']);
    }

    public function test_reports_no_missing_types_when_all_present(): void
    {
        $project = $this->makeProject();

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'name' => 'Final Review',
            'required_document_types' => ['report'],
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'scope_type' => 'project',
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'work_instance_id' => (string) $instance->id,
            'work_template_step_id' => (string) $templateStep->id,
            'name' => 'Final Review',
        ]);

        Document::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'document_type' => 'report',
        ]);

        $report = (new DocumentChecklistService())->buildReport($project);

        $this->assertCount(1, $report);
        $this->assertSame([], $report[0]['missing']);
    }

    public function test_omits_steps_with_no_required_document_types(): void
    {
        $project = $this->makeProject();

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'required_document_types' => null,
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'scope_type' => 'project',
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'work_instance_id' => (string) $instance->id,
            'work_template_step_id' => (string) $templateStep->id,
        ]);

        $report = (new DocumentChecklistService())->buildReport($project);

        $this->assertSame([], $report);
    }

    public function test_ignores_work_instances_scoped_to_component(): void
    {
        $project = $this->makeProject();

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'required_document_types' => ['photo'],
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'scope_type' => 'component',
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'work_instance_id' => (string) $instance->id,
            'work_template_step_id' => (string) $templateStep->id,
        ]);

        $report = (new DocumentChecklistService())->buildReport($project);

        $this->assertSame([], $report);
    }

    public function test_ignores_documents_from_another_project(): void
    {
        $project = $this->makeProject();
        $otherProject = Project::factory()->create(['tenant_id' => (string) $project->tenant_id]);

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'required_document_types' => ['drawing'],
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'scope_type' => 'project',
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'work_instance_id' => (string) $instance->id,
            'work_template_step_id' => (string) $templateStep->id,
        ]);

        Document::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $otherProject->id,
            'document_type' => 'drawing',
        ]);

        $report = (new DocumentChecklistService())->buildReport($project);

        $this->assertSame(['drawing'], $report[0]['missing']);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=DocumentChecklistServiceTest`
Expected: FAIL — `Class "App\Services\DocumentChecklistService" not found`

- [ ] **Step 3: Implement `DocumentChecklistService`**

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\Project;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;

/**
 * Read-only comparison of a Project's uploaded Documents against the
 * required_document_types declared on its applied WorkTemplate steps
 * (spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md, Phase 9).
 * Pure PHP set comparison — no AI/LLM call. Matching is project-wide
 * (any Document with a matching document_type counts, regardless of step).
 */
class DocumentChecklistService
{
    /**
     * @return list<array{step_id: string, step_name: string, required: list<string>, missing: list<string>}>
     */
    public function buildReport(Project $project): array
    {
        $tenantId = (string) $project->tenant_id;

        $presentDocumentTypes = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', (string) $project->id)
            ->whereIn('document_type', Document::VALID_DOCUMENT_TYPES)
            ->pluck('document_type')
            ->map(fn ($type): string => (string) $type)
            ->unique()
            ->values()
            ->all();

        $instanceIds = WorkInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', (string) $project->id)
            ->where('scope_type', 'project')
            ->pluck('id');

        if ($instanceIds->isEmpty()) {
            return [];
        }

        $steps = WorkInstanceStep::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('work_instance_id', $instanceIds)
            ->with('templateStep')
            ->get();

        $report = [];

        foreach ($steps as $step) {
            $templateStep = $step->templateStep;
            $required = $templateStep?->required_document_types ?? [];

            if (empty($required)) {
                continue;
            }

            $missing = array_values(array_diff($required, $presentDocumentTypes));

            $report[] = [
                'step_id' => (string) $step->id,
                'step_name' => (string) ($step->name ?? $step->step_key),
                'required' => array_values($required),
                'missing' => $missing,
            ];
        }

        return $report;
    }
}
```

- [ ] **Step 4: Add the `templateStep()` relation to `WorkInstanceStep`**

`DocumentChecklistService` calls `$step->templateStep` (Step 3 above), a relation that does not exist yet. In `app/Models/WorkInstanceStep.php`, add this method after the existing `workInstance()` relation:

```php
    public function templateStep(): BelongsTo
    {
        return $this->belongsTo(WorkTemplateStep::class, 'work_template_step_id');
    }
```

`WorkInstanceStep.php` already imports `Illuminate\Database\Eloquent\Relations\BelongsTo` (used by `workInstance()`). No new `use` import is needed for `WorkTemplateStep::class` — both classes share the `App\Models` namespace, so the bare class name resolves without an import, matching how `workInstance()` already references `WorkInstance::class` with no import either.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=DocumentChecklistServiceTest`
Expected: PASS (5/5)

- [ ] **Step 6: Commit**

```bash
git add app/Services/DocumentChecklistService.php app/Models/WorkInstanceStep.php tests/Unit/DocumentChecklistServiceTest.php
git commit -m "feat(document-checklist): add DocumentChecklistService (pure PHP, no AI call)"
```

---

### Task 3: "Checklist tài liệu" card on the Project detail page

**Files:**
- Modify: `app/Http/Controllers/Web/ProjectController.php` (`show()`)
- Modify: `resources/views/projects/show.blade.php`
- Test: `tests/Feature/Web/ProjectDocumentChecklistTest.php` (new)

**Interfaces:**
- Consumes: `App\Services\DocumentChecklistService::buildReport(Project $project): array` (Task 2).
- Produces: nothing further — this is the final UI-rendering task.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/Web/ProjectDocumentChecklistTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Models\WorkTemplateStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ProjectDocumentChecklistTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Issue Drawings',
            'required_document_types' => ['drawing', 'contract'],
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'scope_type' => 'project',
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'work_instance_id' => (string) $instance->id,
            'work_template_step_id' => (string) $templateStep->id,
            'name' => 'Issue Drawings',
        ]);

        Document::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'document_type' => 'drawing',
        ]);
    }

    public function test_shows_checklist_card_with_missing_types_for_authorized_user(): void
    {
        $user = $this->createTenantUser($this->tenant, [], ['admin'], ['work.view']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($user)
            ->get(route('app.projects.show', $this->project->id), $headers);

        $response->assertOk()
            ->assertSee('Checklist tài liệu')
            ->assertSee('Issue Drawings')
            ->assertSee('contract');
    }

    public function test_hides_checklist_card_without_work_view_permission(): void
    {
        $user = $this->createTenantUser($this->tenant, [], ['staff'], []);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($user)
            ->get(route('app.projects.show', $this->project->id), $headers);

        $response->assertOk()
            ->assertDontSee('Checklist tài liệu');
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=ProjectDocumentChecklistTest`
Expected: FAIL — `assertSee('Checklist tài liệu')` fails, the card doesn't exist yet.

- [ ] **Step 3: Wire `DocumentChecklistService` into `Web\ProjectController::show()`**

In `app/Http/Controllers/Web/ProjectController.php`, add `use App\Services\DocumentChecklistService;` to the existing `use` block at the top of the file. Then replace the `show()` method:

```php
    public function show(string $projectId): View
    {
        try {
            $user = Auth::user();

            $project = AppProject::query()
                ->with([
                    'manager',
                    'client',
                    'tasks',
                ])
                ->where('tenant_id', $user?->tenant_id)
                ->findOrFail($projectId);

            $documentChecklist = $user?->hasPermission('work.view')
                ? (new DocumentChecklistService())->buildReport($project)
                : null;

            return view('projects.show', [
                'project' => $project,
                'documentChecklist' => $documentChecklist,
            ]);
        } catch (\Throwable $e) {
            abort(404, 'Dự án không tồn tại.');
        }
    }
```

- [ ] **Step 4: Add the card to `projects.show.blade.php`**

In `resources/views/projects/show.blade.php`, add this new card immediately after the existing `<x-ui.card title="Công việc ...">` block's closing `</x-ui.card>` tag, and before the final `<div class="flex flex-wrap gap-3">` block:

```blade
    @if ($documentChecklist !== null)
        <x-ui.card title="Checklist tài liệu">
            @if (empty($documentChecklist))
                <p class="text-sm text-slate-500">Không có yêu cầu tài liệu nào cho dự án này.</p>
            @else
                <x-ui.data-table :headers="['Bước', 'Yêu cầu', 'Còn thiếu']">
                    @foreach ($documentChecklist as $row)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $row['step_name'] }}</td>
                            <td class="text-sm text-slate-600">{{ implode(', ', $row['required']) }}</td>
                            <td class="text-sm">
                                @if (empty($row['missing']))
                                    <span class="text-emerald-600">Đủ</span>
                                @else
                                    <span class="text-amber-600">{{ implode(', ', $row['missing']) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </x-ui.card>
    @endif
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=ProjectDocumentChecklistTest`
Expected: PASS (2/2)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/ProjectController.php resources/views/projects/show.blade.php tests/Feature/Web/ProjectDocumentChecklistTest.php
git commit -m "feat(projects): add read-only Checklist tài liệu card to the project detail page"
```

---

### Task 4: Full suite + Deptrac verification

**Files:** None (verification only).

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: All tests pass. Baseline before this phase was 1441 passed, 11 skipped, 0 failed. Expect that plus the ~11 new tests added across Tasks 1-3.

- [ ] **Step 2: Run Deptrac**

Run: `vendor/bin/deptrac analyse --no-cache`
Expected: No new violations (0 violations, 0 errors, matching the established baseline). `DocumentChecklistService` lives in `app/Services/`, consumed only by `app/Http/Controllers/Web/ProjectController.php` — the same layer relationship as `AiAssistService`'s existing consumers.

- [ ] **Step 3: If either step fails, fix and re-run**

Do not proceed to the final review until both Step 1 and Step 2 are clean.

---

## Post-plan notes for the controller (not a task — read before dispatching)

- This phase makes NO Anthropic API call and needs no `ANTHROPIC_API_KEY` — if a reviewer looks for `AiAssistService` usage and finds none, that is correct, not a missing feature. The "AI" in "AI Use Case 3" is a product-framing label from the roadmap, not a technical requirement of this task.
- Task 1's `Api\WorkTemplateController` changes are validation-and-persistence additions only — do not touch `Api\DocumentController`'s existing inline `document_type` enum validation string; that file uses a different subclass (`Src\DocumentManagement\Models\LegacyDocumentAdapter`, which trivially extends `App\Models\Document` with no overrides) and fixing its validation-string duplication is explicitly out of scope per the spec.
- The `work.view`-gated card visibility is checked in the Blade template (Task 3, Step 4's `@if ($documentChecklist !== null)`), not via route middleware — do not "fix" this by adding `rbac:work.view` to the `app.projects.show` route; that would newly restrict page access for any user who currently can view the page (which has no RBAC gate today) but lacks `work.view`, which is a behavior change beyond this feature's scope.
