# Phase 8 (AI Use Case 2) — DesignItem Description Suggestion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a manual "Gợi ý AI" button to the DesignItem **creation** form. Clicking it sends only the currently-selected `item_type` and (when resolvable) the originating project's `Opportunity.service_category` to Anthropic's Messages API (Claude Haiku 4.5, forced tool-use), and pre-fills a new `description` textarea — the user can freely edit before submitting. The AI never writes to the database; it only pre-fills a client-side form field on a record that does not exist yet.

**Architecture:** Extends the existing `App\Services\AiAssistService` (built in Phase 7) with a second public method, `suggestDesignItemDescription(string $itemType, ?string $serviceCategory): ?array`, reusing the same forced-tool-use / fail-closed-to-null pattern as `suggestLeadConversion()`. A new JSON endpoint on `Web\DesignItemPageController` accepts the create form's in-progress `project_id`/`item_type` selections (not a saved record's ID — the DesignItem doesn't exist yet when the button is clicked), tenant-validates `project_id`, resolves the project's service category via a reverse `Opportunity.converted_project_id` lookup, and calls the service. A new `description` column is added to `design_items` (it doesn't exist today). A new vanilla-JS module follows the exact `ai-lead-suggest.js` IIFE convention.

**Tech Stack:** Laravel 12, existing `AiAssistService` (Anthropic Messages API, `config('ai.*')`), vanilla JS (IIFE, `fetch`, CSRF meta tag), Blade.

## Global Constraints

- **Data minimization (spec-mandated):** the ONLY data ever sent to Anthropic for this use case is `item_type` (a closed-vocabulary enum value) and, when resolvable, `service_category` (also a closed-vocabulary enum value). No project name, no client/account data, no tenant identifiers, no other field.
- **Never trust the AI blindly (spec-mandated):** the returned `description` is validated structurally (non-empty after trimming) before being returned from the service — there is no enum to check it against (unlike Use Case 1's `service_category`), since a free-text description has no fixed vocabulary.
- **Graceful degradation (spec-mandated):** any API failure (missing config, network error, non-2xx, malformed/empty output) must never throw an exception to the controller or break the page. The service returns `?array` (nullable), never throws — identical contract to `suggestLeadConversion()`.
- **Trigger is a manual button, not automatic** — the endpoint is only ever called on explicit user click.
- **Model:** reuses `config('ai.model')` (already `claude-haiku-4-5-20251001` by default from Phase 7) — do not add a second config key.
- **Permission:** reuses the existing `ai.suggest` permission from Phase 7 — do not add a new permission.
- **Scope:** DesignItem **create** form only. There is no edit form wired to the web UI today (`Api\DesignItemController::update()` has no corresponding web route) — do not add one as part of this plan.
- **No new `Project` schema.** "Project type" is resolved at request time from `Opportunity.service_category` via the reverse `converted_project_id` lookup; when no such Opportunity exists, the suggestion proceeds with `item_type` alone.
- **`declare(strict_types=1)`** at the top of every new/modified PHP file, matching the rest of the codebase.

---

### Task 1: `AiAssistService::suggestDesignItemDescription()` + `description` column

**Files:**
- Create: `database/migrations/2026_07_12_090000_add_description_to_design_items_table.php`
- Modify: `app/Models/DesignItem.php` (add `description` to `$fillable`)
- Modify: `app/Services/AiAssistService.php` (extract a shared tool-use-parsing helper, add the new public method)
- Test: `tests/Unit/AiAssistServiceTest.php` (append new test methods to the existing file)

**Interfaces:**
- Consumes: nothing new from other tasks.
- Produces: `App\Services\AiAssistService::suggestDesignItemDescription(string $itemType, ?string $serviceCategory): ?array` — returns `['description' => string]` or `null` on any failure. This is the only symbol later tasks depend on.

- [ ] **Step 1: Write the migration**

Create `database/migrations/2026_07_12_090000_add_description_to_design_items_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_items', function (Blueprint $table) {
            $table->text('description')->nullable()->after('item_type');
        });
    }

    public function down(): void
    {
        Schema::table('design_items', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `2026_07_12_090000_add_description_to_design_items_table ... DONE`

- [ ] **Step 3: Add `description` to `DesignItem::$fillable`**

In `app/Models/DesignItem.php`, find the `protected $fillable = [...]` array (currently `tenant_id`, `project_id`, `work_instance_step_id`, `name`, `item_type`, `review_status`, `assigned_to`, `due_to_client_at`, `client_feedback_notes`, `approval_evidence`, `created_by`) and add `'description'` immediately after `'item_type'`:

```php
    protected $fillable = [
        'tenant_id',
        'project_id',
        'work_instance_step_id',
        'name',
        'item_type',
        'description',
        'review_status',
        'assigned_to',
        'due_to_client_at',
        'client_feedback_notes',
        'approval_evidence',
        'created_by',
    ];
```

- [ ] **Step 4: Write the failing unit tests for the new service method**

Open `tests/Unit/AiAssistServiceTest.php` (already exists from Phase 7). Append these 8 test methods inside the existing `AiAssistServiceTest` class, immediately before the final closing `}` of the class:

```php
    public function test_suggests_design_item_description_with_service_category(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_design_item_description',
                    'input' => ['description' => 'Bản vẽ phối cảnh mặt tiền theo phong cách hiện đại.'],
                ]],
            ], 200),
        ]);

        $result = (new AiAssistService())->suggestDesignItemDescription('concept', 'architecture');

        $this->assertSame(['description' => 'Bản vẽ phối cảnh mặt tiền theo phong cách hiện đại.'], $result);
    }

    public function test_suggests_design_item_description_without_service_category(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_design_item_description',
                    'input' => ['description' => 'Mô tả kỹ thuật cho hạng mục.'],
                ]],
            ], 200),
        ]);

        $result = (new AiAssistService())->suggestDesignItemDescription('technical', null);

        $this->assertSame(['description' => 'Mô tả kỹ thuật cho hạng mục.'], $result);
    }

    public function test_design_item_suggestion_sends_only_item_type_and_service_category(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_design_item_description',
                    'input' => ['description' => 'Tóm tắt.'],
                ]],
            ], 200),
        ]);

        (new AiAssistService())->suggestDesignItemDescription('mep', 'construction');

        Http::assertSent(function ($request) {
            $content = $request->data()['messages'][0]['content'];
            $this->assertStringContainsString('mep', $content);
            $this->assertStringContainsString('construction', $content);

            $encoded = json_encode($request->data());
            $this->assertStringNotContainsString('project_id', (string) $encoded);
            $this->assertStringNotContainsString('tenant', strtolower((string) $encoded));

            return true;
        });
    }

    public function test_returns_null_when_design_item_api_key_missing(): void
    {
        config(['ai.anthropic_api_key' => null]);

        $result = (new AiAssistService())->suggestDesignItemDescription('concept', 'architecture');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_returns_null_when_item_type_blank(): void
    {
        $result = (new AiAssistService())->suggestDesignItemDescription('   ', 'architecture');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_returns_null_when_design_item_response_not_successful(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => 'rate limited'], 429)]);

        $this->assertNull((new AiAssistService())->suggestDesignItemDescription('concept', 'architecture'));
    }

    public function test_returns_null_when_design_item_description_empty(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_design_item_description',
                    'input' => ['description' => ''],
                ]],
            ], 200),
        ]);

        $this->assertNull((new AiAssistService())->suggestDesignItemDescription('concept', 'architecture'));
    }

    public function test_returns_null_when_design_item_connection_fails(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->assertNull((new AiAssistService())->suggestDesignItemDescription('concept', 'architecture'));
    }
```

- [ ] **Step 5: Run the tests to verify they fail**

Run: `php artisan test --filter=AiAssistServiceTest`
Expected: FAIL — `Call to undefined method App\Services\AiAssistService::suggestDesignItemDescription()`

- [ ] **Step 6: Refactor `AiAssistService` to extract a shared tool-use-parsing helper**

In `app/Services/AiAssistService.php`, replace the existing private `extractSuggestion()` method with a generic helper plus a thinner `extractSuggestion()` that calls it — this keeps `suggestLeadConversion()`'s behavior byte-identical (same public return values for every existing test case) while giving the new method something to reuse. Replace:

```php
    /**
     * @param array<int, mixed> $contentBlocks
     * @return array{service_category: string, scope_summary: string}|null
     */
    private function extractSuggestion(array $contentBlocks): ?array
    {
        $toolUse = null;
        foreach ($contentBlocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'tool_use') {
                $toolUse = $block;
                break;
            }
        }

        if ($toolUse === null || !isset($toolUse['input']) || !is_array($toolUse['input'])) {
            return null;
        }

        $category = (string) ($toolUse['input']['service_category'] ?? '');
        $summary = trim((string) ($toolUse['input']['scope_summary'] ?? ''));

        if (!in_array($category, Opportunity::VALID_SERVICE_CATEGORIES, true) || $summary === '') {
            Log::warning('ai_assist.lead_suggestion_invalid_output', ['service_category' => $category]);

            return null;
        }

        return ['service_category' => $category, 'scope_summary' => $summary];
    }
```

with:

```php
    /**
     * @param array<int, mixed> $contentBlocks
     * @return array{service_category: string, scope_summary: string}|null
     */
    private function extractSuggestion(array $contentBlocks): ?array
    {
        $input = $this->extractToolUseInput($contentBlocks, self::TOOL_NAME);

        if ($input === null) {
            return null;
        }

        $category = (string) ($input['service_category'] ?? '');
        $summary = trim((string) ($input['scope_summary'] ?? ''));

        if (!in_array($category, Opportunity::VALID_SERVICE_CATEGORIES, true) || $summary === '') {
            Log::warning('ai_assist.lead_suggestion_invalid_output', ['service_category' => $category]);

            return null;
        }

        return ['service_category' => $category, 'scope_summary' => $summary];
    }

    /**
     * @param array<int, mixed> $contentBlocks
     * @return array<string, mixed>|null
     */
    private function extractToolUseInput(array $contentBlocks, string $toolName): ?array
    {
        foreach ($contentBlocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === $toolName) {
                $input = $block['input'] ?? null;

                return is_array($input) ? $input : null;
            }
        }

        return null;
    }
```

- [ ] **Step 7: Add the `DESIGN_ITEM_TOOL_NAME` constant and `suggestDesignItemDescription()` method**

In the same file, add a second constant immediately after the existing `private const TOOL_NAME = 'suggest_lead_conversion';`:

```php
    private const DESIGN_ITEM_TOOL_NAME = 'suggest_design_item_description';
```

Then add this new public method immediately after `suggestLeadConversion()` (after its closing `}`, before `extractSuggestion()`):

```php
    /**
     * @return array{description: string}|null
     */
    public function suggestDesignItemDescription(string $itemType, ?string $serviceCategory): ?array
    {
        $apiKey = (string) config('ai.anthropic_api_key');

        if ($apiKey === '' || trim($itemType) === '') {
            return null;
        }

        $context = ($serviceCategory !== null && trim($serviceCategory) !== '')
            ? "Design item type: {$itemType}. Project service category: {$serviceCategory}."
            : "Design item type: {$itemType}.";

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post(self::ENDPOINT, [
                    'model' => (string) config('ai.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => 512,
                    'tools' => [[
                        'name' => self::DESIGN_ITEM_TOOL_NAME,
                        'description' => 'Suggest a short Vietnamese description for a design work item, given its type and, when known, the project service category.',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'description' => [
                                    'type' => 'string',
                                    'description' => 'A short (1-2 sentence) Vietnamese description of the design work item, suitable to save on the record.',
                                ],
                            ],
                            'required' => ['description'],
                        ],
                    ]],
                    'tool_choice' => ['type' => 'tool', 'name' => self::DESIGN_ITEM_TOOL_NAME],
                    'messages' => [[
                        'role' => 'user',
                        'content' => $context,
                    ]],
                ]);

            if (!$response->successful()) {
                Log::warning('ai_assist.design_item_suggestion_failed', ['status' => $response->status()]);

                return null;
            }

            $input = $this->extractToolUseInput($response->json('content', []), self::DESIGN_ITEM_TOOL_NAME);

            if ($input === null) {
                return null;
            }

            $description = trim((string) ($input['description'] ?? ''));

            if ($description === '') {
                Log::warning('ai_assist.design_item_suggestion_invalid_output');

                return null;
            }

            return ['description' => $description];
        } catch (Throwable $e) {
            Log::error('ai_assist.design_item_suggestion_exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
```

- [ ] **Step 8: Run the full `AiAssistServiceTest` file to verify all pass (old and new)**

Run: `php artisan test --filter=AiAssistServiceTest`
Expected: PASS (18/18 — the 10 original Use Case 1 tests plus the 8 new ones; the refactor in Step 6 must not change `suggestLeadConversion()`'s behavior for any of the original 10)

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_07_12_090000_add_description_to_design_items_table.php app/Models/DesignItem.php app/Services/AiAssistService.php tests/Unit/AiAssistServiceTest.php
git commit -m "feat(ai): add description column to design_items and AiAssistService::suggestDesignItemDescription()"
```

---

### Task 2: Suggestion endpoint on `DesignItemPageController`

**Files:**
- Modify: `app/Http/Controllers/Web/DesignItemPageController.php` (new `suggestDescription()` method + imports)
- Modify: `routes/web.php` (new route)
- Test: `tests/Feature/Zena/AiDesignItemSuggestionTest.php` (new file)

**Interfaces:**
- Consumes: `App\Services\AiAssistService::suggestDesignItemDescription(string $itemType, ?string $serviceCategory): ?array` (Task 1).
- Produces: `POST /operator/design-items/suggest-description` → JSON `{"success": true, "data": {"description": string}}` on success, or `{"success": false, "message": string}` (422 for invalid input, 503 when no suggestion is available). Route name: `operator.design-items.suggest-description`. Request body: `{"project_id": string, "item_type": string}`.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/Zena/AiDesignItemSuggestionTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class AiDesignItemSuggestionTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        config(['ai.anthropic_api_key' => 'test-key']);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['design-item.view', 'design-item.manage', 'ai.suggest']
        );
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
    }

    public function test_returns_suggestion_for_authorized_user(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_design_item_description',
                    'input' => ['description' => 'Bản vẽ mặt bằng tầng 1.'],
                ]],
            ], 200),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk();

        $response = $this->actingAs($this->user)
            ->post(route('operator.design-items.suggest-description'), [
                'project_id' => (string) $this->project->id,
                'item_type' => 'concept',
            ], $headers);

        $response->assertOk()->assertJson([
            'success' => true,
            'data' => ['description' => 'Bản vẽ mặt bằng tầng 1.'],
        ]);
    }

    public function test_resolves_service_category_from_originating_opportunity(): void
    {
        Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => \App\Models\Account::query()->create([
                'tenant_id' => (string) $this->tenant->id,
                'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
                'display_name' => 'Khach hang',
                'status' => \App\Models\Account::STATUS_ACTIVE,
            ])->id,
            'opportunity_name' => 'Co hoi lien ket project',
            'service_category' => 'interior',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
            'converted_project_id' => (string) $this->project->id,
        ]);

        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_design_item_description',
                    'input' => ['description' => 'Mô tả nội thất.'],
                ]],
            ], 200),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk();

        $this->actingAs($this->user)
            ->post(route('operator.design-items.suggest-description'), [
                'project_id' => (string) $this->project->id,
                'item_type' => 'interior',
            ], $headers);

        Http::assertSent(function ($request) {
            $content = $request->data()['messages'][0]['content'];

            return str_contains($content, 'interior');
        });
    }

    public function test_returns_503_when_ai_service_unavailable(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => 'down'], 500)]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk();

        $response = $this->actingAs($this->user)
            ->post(route('operator.design-items.suggest-description'), [
                'project_id' => (string) $this->project->id,
                'item_type' => 'concept',
            ], $headers);

        $response->assertStatus(503)->assertJson(['success' => false]);
    }

    public function test_denied_without_ai_suggest_permission(): void
    {
        $staff = $this->createTenantUser($this->tenant, [], ['staff'], ['design-item.view', 'design-item.manage']);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($staff)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk();

        $response = $this->actingAs($staff)
            ->post(route('operator.design-items.suggest-description'), [
                'project_id' => (string) $this->project->id,
                'item_type' => 'concept',
            ], $headers);

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_denied_without_design_item_manage_permission(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['viewer'], ['design-item.view', 'ai.suggest']);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($viewer)
            ->get(route('operator.design-items.index'), $headers)
            ->assertOk();

        $response = $this->actingAs($viewer)
            ->post(route('operator.design-items.suggest-description'), [
                'project_id' => (string) $this->project->id,
                'item_type' => 'concept',
            ], $headers);

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_returns_422_for_project_in_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['admin'], ['design-item.view', 'design-item.manage', 'ai.suggest']);

        $headers = ['X-Tenant-ID' => (string) $otherTenant->id];

        $this->actingAs($otherUser)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk();

        $response = $this->actingAs($otherUser)
            ->post(route('operator.design-items.suggest-description'), [
                'project_id' => (string) $this->project->id,
                'item_type' => 'concept',
            ], $headers);

        $response->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_requires_authentication(): void
    {
        $this->post(route('operator.design-items.suggest-description'))
            ->assertRedirect();
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=AiDesignItemSuggestionTest`
Expected: FAIL — route `operator.design-items.suggest-description` not defined

- [ ] **Step 3: Add the route**

In `routes/web.php`, immediately after the existing `design-items.documents.store` route (in the "Design Item" block):

```php
    Route::post('/design-items/suggest-description', [App\Http\Controllers\Web\DesignItemPageController::class, 'suggestDescription'])->middleware(['rbac:design-item.manage', 'rbac:ai.suggest'])->name('design-items.suggest-description');
```

- [ ] **Step 4: Add imports and the `suggestDescription()` method to `DesignItemPageController`**

In `app/Http/Controllers/Web/DesignItemPageController.php`, add these imports to the existing `use` block at the top of the file (alongside the existing imports — insert alphabetically is fine, exact position doesn't matter):

```php
use App\Models\Opportunity;
use App\Services\AiAssistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
```

Then add this new method anywhere inside the class (after `create()` is a natural place, before `store()`):

```php
    public function suggestDescription(Request $request, AiAssistService $aiAssistService): JsonResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $validator = Validator::make($request->all(), [
            'project_id' => [
                'required',
                'string',
                Rule::exists('projects', 'id')->where('tenant_id', $tenantId),
            ],
            'item_type' => ['required', Rule::in(DesignItem::VALID_TYPES)],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.'], 422);
        }

        $projectId = (string) $request->input('project_id');
        $itemType = (string) $request->input('item_type');

        $serviceCategory = Opportunity::query()
            ->where('converted_project_id', $projectId)
            ->value('service_category');

        $suggestion = $aiAssistService->suggestDesignItemDescription($itemType, $serviceCategory);

        if ($suggestion === null) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo gợi ý lúc này.',
            ], 503);
        }

        return response()->json(['success' => true, 'data' => $suggestion]);
    }
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=AiDesignItemSuggestionTest`
Expected: PASS (7/7)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/DesignItemPageController.php routes/web.php tests/Feature/Zena/AiDesignItemSuggestionTest.php
git commit -m "feat(design-items): add AI description-suggestion endpoint reusing ai.suggest permission"
```

---

### Task 3: Description field + "Gợi ý AI" button + JS module

**Files:**
- Modify: `app/Http/Controllers/Api/DesignItemController.php` (`rules()`, `store()`, `update()`, `RESPONSE_FIELDS` — accept/return `description`)
- Modify: `app/Http/Controllers/Web/DesignItemPageController.php` (`store()` — pass `description` through)
- Modify: `resources/views/design-items/create.blade.php` (new field + button)
- Create: `resources/js/ai-design-item-suggest.js`
- Modify: `resources/views/layouts/operator.blade.php` (load the new module)
- Modify: `vite.config.js` (register the new entry)
- Test: extend `tests/Feature/Zena/OperatorDesignItemUiTest.php`

**Interfaces:**
- Consumes: `POST operator.design-items.suggest-description` (Task 2) — JSON `{success, data: {description}}`.
- Produces: `description` now flows through `Web\DesignItemPageController::store()` → `Api\DesignItemController::store()` → persisted on `DesignItem.description`.

- [ ] **Step 1: Write the failing feature test for the new field**

In `tests/Feature/Zena/OperatorDesignItemUiTest.php`, add this test method (after `test_design_item_ui_full_flow`):

```php
    public function test_design_item_creation_accepts_description(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $create = $this->actingAs($this->user)
            ->post(route('operator.design-items.store'), [
                'project_id' => (string) $this->project->id,
                'name' => 'Phoi canh san vuon',
                'item_type' => 'concept',
                'description' => 'Mo ta duoc nhap thu cong hoac tu AI.',
            ], $headers);

        $item = DesignItem::query()->where('name', 'Phoi canh san vuon')->firstOrFail();
        $create->assertRedirect(route('operator.design-items.show', $item->id));
        $this->assertSame('Mo ta duoc nhap thu cong hoac tu AI.', $item->description);
    }

    public function test_design_item_creation_allows_blank_description(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $create = $this->actingAs($this->user)
            ->post(route('operator.design-items.store'), [
                'project_id' => (string) $this->project->id,
                'name' => 'Khong co mo ta',
                'item_type' => 'concept',
            ], $headers);

        $item = DesignItem::query()->where('name', 'Khong co mo ta')->firstOrFail();
        $create->assertRedirect(route('operator.design-items.show', $item->id));
        $this->assertNull($item->description);
    }

    public function test_design_items_create_page_shows_ai_suggest_button(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk()
            ->assertSee('Gợi ý AI')
            ->assertSee('description', false);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=OperatorDesignItemUiTest`
Expected: FAIL — `test_design_item_creation_accepts_description` fails because `description` is not yet accepted; `test_design_items_create_page_shows_ai_suggest_button` fails because the button/field don't exist yet.

- [ ] **Step 3: Update `Api\DesignItemController` to accept and return `description`**

In `app/Http/Controllers/Api/DesignItemController.php`, add `'description'` to the `RESPONSE_FIELDS` array (after `'item_type'`, around line 36):

```php
    private const RESPONSE_FIELDS = [
        'id',
        'tenant_id',
        'project_id',
        'work_instance_step_id',
        'name',
        'item_type',
        'description',
        'review_status',
        'assigned_to',
        'due_to_client_at',
        'client_feedback_notes',
        'approval_evidence',
        'created_by',
        'created_at',
        'updated_at',
    ];
```

In the same file's `rules()` method (around line 69-91), add a validation rule after `item_type`:

```php
    private function rules(string $tenantId): array
    {
        return [
            'project_id' => [
                'required',
                'string',
                Rule::exists('projects', 'id')->where('tenant_id', $tenantId),
            ],
            'work_instance_step_id' => [
                'nullable',
                'string',
                Rule::exists('work_instance_steps', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'item_type' => ['nullable', Rule::in(DesignItem::VALID_TYPES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => [
                'nullable',
                'string',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'due_to_client_at' => ['nullable', 'date'],
        ];
    }
```

In `store()` (around line 146-156), add `description` to the `DesignItem::query()->create([...])` call:

```php
        $item = DesignItem::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => (string) $request->input('project_id'),
            'work_instance_step_id' => $request->input('work_instance_step_id'),
            'name' => (string) $request->input('name'),
            'item_type' => (string) $request->input('item_type', DesignItem::TYPE_OTHER),
            'description' => $request->input('description'),
            'review_status' => DesignItem::STATUS_DRAFT,
            'assigned_to' => $request->input('assigned_to'),
            'due_to_client_at' => $request->input('due_to_client_at'),
            'created_by' => (string) $user->id,
        ]);
```

In `update()` (around line 218-220), add `'description'` to the `$request->only([...])` list:

```php
        $item->fill($request->only([
            'project_id', 'work_instance_step_id', 'name', 'item_type', 'description', 'assigned_to', 'due_to_client_at',
        ]));
```

- [ ] **Step 4: Update `Web\DesignItemPageController::store()` to pass `description` through**

In `app/Http/Controllers/Web/DesignItemPageController.php`, in `store()`, add `description` to the `$request->validate([...])` call:

```php
    public function store(Request $request, ApiDesignItemController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'work_instance_step_id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'item_type' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'string'],
            'due_to_client_at' => ['nullable', 'date'],
        ]);
```

The rest of the method (the `$apiController->store(...)` call and redirect logic) is unchanged — `array_filter($validated, ...)` already forwards every present key.

- [ ] **Step 5: Run the tests to verify the description tests pass**

Run: `php artisan test --filter=OperatorDesignItemUiTest`
Expected: `test_design_item_creation_accepts_description` and `test_design_item_creation_allows_blank_description` now PASS. `test_design_items_create_page_shows_ai_suggest_button` still FAILS (UI not added yet).

- [ ] **Step 6: Add the description field and button to `resources/views/design-items/create.blade.php`**

Replace the entire `<form>` block (currently lines 22-48) with:

```blade
        <form method="POST" action="{{ route('operator.design-items.store') }}" class="space-y-4" data-ai-design-item-suggest-form>
            @csrf
            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="project_id">Dự án <span class="text-rose-600">*</span></label>
                    <select id="project_id" name="project_id" class="operator-select" required>
                        <option value="">-- Chọn dự án --</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') === $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="operator-field">
                    <label for="item_type">Loại</label>
                    <select id="item_type" name="item_type" class="operator-select">
                        @foreach (['concept' => 'Ý tưởng', 'schematic' => 'Sơ bộ', 'technical' => 'Kỹ thuật', 'structural' => 'Kết cấu', 'mep' => 'MEP', 'interior' => 'Nội thất', 'other' => 'Khác'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('item_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="operator-field">
                <label for="name">Tên công việc <span class="text-rose-600">*</span></label>
                <input id="name" name="name" type="text" class="operator-input" value="{{ old('name') }}" required placeholder="vd: Phối cảnh mặt tiền phương án 2">
            </div>
            <div class="operator-field">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" class="operator-textarea" data-ai-field="description" placeholder="Mô tả công việc thiết kế (có thể để trống, hoặc dùng Gợi ý AI)">{{ old('description') }}</textarea>
                <button type="button" class="operator-button operator-button-secondary" data-ai-suggest-trigger>Gợi ý AI</button>
                <span class="text-xs text-slate-500" data-ai-suggest-status></span>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Tạo</button>
        </form>
```

- [ ] **Step 7: Create the JS module**

Create `resources/js/ai-design-item-suggest.js`:

```javascript
/**
 * Nút "Gợi ý AI" trên form tạo công việc thiết kế: gọi endpoint gợi ý AI
 * với project_id/item_type đang chọn (chưa lưu DB) và điền sẵn description —
 * người dùng vẫn có thể sửa trước khi submit.
 */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function attach(form) {
        if (form.dataset.aiSuggestBound) return;
        form.dataset.aiSuggestBound = '1';

        var trigger = form.querySelector('[data-ai-suggest-trigger]');
        var statusEl = form.querySelector('[data-ai-suggest-status]');
        var descriptionField = form.querySelector('[data-ai-field="description"]');
        var projectField = form.querySelector('#project_id');
        var itemTypeField = form.querySelector('#item_type');

        if (!trigger) return;

        trigger.addEventListener('click', function () {
            var projectId = projectField ? projectField.value : '';
            var itemType = itemTypeField ? itemTypeField.value : '';

            if (!projectId) {
                if (statusEl) statusEl.textContent = 'Vui lòng chọn dự án trước.';
                return;
            }

            trigger.disabled = true;
            if (statusEl) statusEl.textContent = 'Đang tạo gợi ý...';

            fetch('/operator/design-items/suggest-description', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ project_id: projectId, item_type: itemType }),
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.body.success) {
                        if (statusEl) statusEl.textContent = 'Không thể tạo gợi ý lúc này.';
                        return;
                    }

                    if (descriptionField) descriptionField.value = result.body.data.description;
                    if (statusEl) statusEl.textContent = 'Đã điền gợi ý — bạn có thể chỉnh sửa.';
                })
                .catch(function () {
                    if (statusEl) statusEl.textContent = 'Không thể tạo gợi ý lúc này.';
                })
                .finally(function () {
                    trigger.disabled = false;
                });
        });
    }

    function init() {
        document.querySelectorAll('[data-ai-design-item-suggest-form]').forEach(attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

- [ ] **Step 8: Register the new JS module in Vite and the operator layout**

In `vite.config.js`, add `'resources/js/ai-design-item-suggest.js'` to the existing `input` array (which already contains `resources/js/ai-lead-suggest.js` from Phase 7):

```javascript
            input: ['resources/css/app.css', 'resources/css/operator.css', 'resources/js/app.js', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js', 'resources/js/ai-design-item-suggest.js'],
```

In `resources/views/layouts/operator.blade.php`, add it to the `@vite([...])` call:

```blade
    @vite(['resources/css/operator.css', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js', 'resources/js/ai-design-item-suggest.js'])
```

- [ ] **Step 9: Run the full `OperatorDesignItemUiTest` file to verify all pass**

Run: `php artisan test --filter=OperatorDesignItemUiTest`
Expected: PASS (all methods, including the 3 new ones)

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Api/DesignItemController.php app/Http/Controllers/Web/DesignItemPageController.php resources/views/design-items/create.blade.php resources/js/ai-design-item-suggest.js resources/views/layouts/operator.blade.php vite.config.js tests/Feature/Zena/OperatorDesignItemUiTest.php
git commit -m "feat(design-items): add description field and Gợi ý AI button to creation form"
```

---

### Task 4: Full suite + Deptrac verification

**Files:** None (verification only).

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: All tests pass. Baseline before this phase was 1422 passed, 11 skipped, 1 pre-existing unrelated failure (`PerformanceTest::large dataset handling`, a flaky wall-clock timing test, confirmed unrelated to any AI/CRM/design-item work). Expect that plus the ~18 new tests added across Tasks 1-3, with the same pre-existing flaky failure still possible (not a regression if so — re-run `--filter=PerformanceTest` in isolation to confirm if it recurs).

- [ ] **Step 2: Run Deptrac**

Run: `vendor/bin/deptrac analyse --no-cache`
Expected: No new violations (0 violations, 0 errors, matching Phase 7's baseline). `AiAssistService` already exists in `app/Services/`; this phase only adds a second method to it and a second consumer (`Web\DesignItemPageController`) following the exact same layer relationship as `Web\CrmPageController`'s existing usage.

- [ ] **Step 3: If either step fails, fix and re-run**

Do not proceed to the final review until both Step 1 and Step 2 are clean.

---

## Post-plan notes for the controller (not a task — read before dispatching)

- Task 2's endpoint validates `project_id` against the CURRENT tenant via `Rule::exists('projects', 'id')->where('tenant_id', $tenantId)` — this is the tenant boundary, since (unlike Phase 7's Lead-based endpoint) there is no persisted record ID to path-scope by. A reviewer should specifically verify a cross-tenant `project_id` is rejected (Task 2's test `test_returns_422_for_project_in_another_tenant` covers this).
- The `ai.suggest` + `design-item.manage` dual-permission gate mirrors the exact same rationale as Phase 7's `ai.suggest` + `crm.manage` gate — do not treat this as needing fresh justification each time; it is now an established pattern for any "invoke a paid AI call" endpoint in this codebase.
- Do not add an edit/update UI for DesignItem as part of this plan even though `Api\DesignItemController::update()` now also accepts `description` (Task 3, Step 3) — that endpoint update is necessary for API-level completeness/consistency (an API consumer updating a DesignItem should be able to set `description` too) but wiring a *web* edit form is out of scope, per the spec's explicit create-only UI surface decision.
