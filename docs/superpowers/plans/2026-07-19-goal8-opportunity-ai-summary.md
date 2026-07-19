# AI Opportunity Summary Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A "Tóm tắt AI" button on the CRM opportunity page that produces an ephemeral, anonymized, AI-generated pre-meeting summary of the opportunity's lead origin, appointments, and quotes.

**Architecture:** Third method on the existing `AiAssistService` (Anthropic tool-use, fail-safe null), one new POST endpoint on `CrmPageController` with a separately testable anonymized-context builder, one Blade card + one vanilla-JS file copying the `ai-lead-suggest.js` pattern. No migration, no new permission.

**Tech Stack:** Laravel 12, Anthropic Messages API (`Http` facade), Blade + vanilla JS (Vite), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-19-goal8-opportunity-ai-summary-design.md`

## Global Constraints

- Anonymization is a **whitelist**: the context array sent to the AI contains ONLY the fields listed in Task 2's `buildOpportunitySummaryContext()` code. It must never contain `opportunity_name`, appointment `location`, or any account identity field (name/email/phone). The unit test asserts exact keys.
- Response contract copies `suggestLeadConversion` exactly: success → `200 {"success": true, "data": ...}`; AI disabled/failed → `503 {"success": false, "message": "Không thể tạo tóm tắt lúc này."}`; not found → `404 {"success": false, "message": "Opportunity not found"}`.
- Route middleware: `['rbac:crm.view', 'rbac:ai.suggest', 'throttle:ai-suggest']` — deliberately `crm.view` (read-only action), NOT `crm.manage`.
- No new permission, no migration, no change to the two existing `AiAssistService` methods.
- Nothing is persisted — the summary lives only in the HTTP response.
- Run tests with `./vendor/bin/phpunit <path>` — never `php artisan test` in a hybrid-vendor worktree.

---

### Task 1: `AiAssistService::summarizeOpportunity()`

**Files:**
- Modify: `app/Services/AiAssistService.php` (add one const near line 20, one public method after `suggestDesignItemDescription()` which ends around line 155)
- Test: `tests/Unit/AiAssistServiceTest.php` (append tests inside the existing class)

**Interfaces:**
- Consumes: existing private helper `extractToolUseInput(array $contentBlocks, string $toolName): ?array` (already in the service — do not duplicate it).
- Produces: `public function summarizeOpportunity(array $context): ?array` returning `array{summary: string}|null`. Task 3's controller calls this.

- [ ] **Step 1: Write the failing tests**

Append inside the existing class in `tests/Unit/AiAssistServiceTest.php` (before the final closing brace). Note the existing file already imports `Illuminate\Support\Facades\Http` and defines the service under test — follow its existing conventions for instantiating the service:

```php
    public function test_summarize_opportunity_returns_summary(): void
    {
        config(['ai.anthropic_api_key' => 'test-key']);
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'summarize_opportunity',
                    'input' => ['summary' => "- Cơ hội nội thất, giai đoạn proposal_sent\n- Đã có 2 cuộc hẹn"],
                ]],
            ], 200),
        ]);

        $result = (new \App\Services\AiAssistService())->summarizeOpportunity([
            'opportunity' => ['service_category' => 'interior', 'pipeline_stage' => 'proposal_sent'],
            'appointments' => [],
            'quotes' => [],
        ]);

        $this->assertIsArray($result);
        $this->assertStringContainsString('proposal_sent', $result['summary']);
    }

    public function test_summarize_opportunity_returns_null_without_api_key(): void
    {
        config(['ai.anthropic_api_key' => '']);

        $result = (new \App\Services\AiAssistService())->summarizeOpportunity([
            'opportunity' => ['service_category' => 'interior'],
        ]);

        $this->assertNull($result);
    }

    public function test_summarize_opportunity_returns_null_on_api_error(): void
    {
        config(['ai.anthropic_api_key' => 'test-key']);
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response(['error' => 'overloaded'], 529),
        ]);

        $result = (new \App\Services\AiAssistService())->summarizeOpportunity([
            'opportunity' => ['service_category' => 'interior'],
        ]);

        $this->assertNull($result);
    }

    public function test_summarize_opportunity_returns_null_on_empty_summary(): void
    {
        config(['ai.anthropic_api_key' => 'test-key']);
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'summarize_opportunity',
                    'input' => ['summary' => '   '],
                ]],
            ], 200),
        ]);

        $result = (new \App\Services\AiAssistService())->summarizeOpportunity([
            'opportunity' => ['service_category' => 'interior'],
        ]);

        $this->assertNull($result);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/AiAssistServiceTest.php --filter summarize_opportunity`
Expected: FAIL — `Call to undefined method App\Services\AiAssistService::summarizeOpportunity()`.

- [ ] **Step 3: Implement the method**

In `app/Services/AiAssistService.php`, add below the existing const declarations (after `private const DESIGN_ITEM_TOOL_NAME = 'suggest_design_item_description';`):

```php
    private const SUMMARY_TOOL_NAME = 'summarize_opportunity';
```

Then add this method after `suggestDesignItemDescription()` (before the private helpers):

```php
    /**
     * Summarize an anonymized CRM opportunity context for pre-meeting preparation.
     * The caller (CrmPageController::buildOpportunitySummaryContext) is responsible for
     * anonymization — this method sends $context verbatim as JSON and must only ever
     * receive already-whitelisted data.
     *
     * @param array<string, mixed> $context
     * @return array{summary: string}|null
     */
    public function summarizeOpportunity(array $context): ?array
    {
        $apiKey = (string) config('ai.anthropic_api_key');

        if ($apiKey === '' || $context === []) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post(self::ENDPOINT, [
                    'model' => (string) config('ai.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => 1024,
                    'system' => 'Bạn tóm tắt một cơ hội bán hàng (CRM) cho người sale chuẩn bị gặp khách. '
                        . 'Viết tiếng Việt, 5-7 gạch đầu dòng, theo khung: tình trạng hiện tại → lịch sử tương tác → tình trạng báo giá → điểm cần lưu ý trước cuộc gặp. '
                        . 'CHỈ dùng dữ kiện được cung cấp trong JSON. KHÔNG suy diễn hay bịa số liệu. '
                        . 'Dữ kiện nào thiếu thì ghi "chưa có thông tin".',
                    'tools' => [[
                        'name' => self::SUMMARY_TOOL_NAME,
                        'description' => 'Return the Vietnamese pre-meeting summary of the CRM opportunity.',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'summary' => [
                                    'type' => 'string',
                                    'description' => '5-7 Vietnamese bullet lines summarizing the opportunity.',
                                ],
                            ],
                            'required' => ['summary'],
                        ],
                    ]],
                    'tool_choice' => ['type' => 'tool', 'name' => self::SUMMARY_TOOL_NAME],
                    'messages' => [[
                        'role' => 'user',
                        'content' => (string) json_encode($context, JSON_UNESCAPED_UNICODE),
                    ]],
                ]);

            if (!$response->successful()) {
                Log::warning('ai_assist.opportunity_summary_failed', ['status' => $response->status()]);

                return null;
            }

            $input = $this->extractToolUseInput($response->json('content', []), self::SUMMARY_TOOL_NAME);

            if ($input === null) {
                return null;
            }

            $summary = trim((string) ($input['summary'] ?? ''));

            if ($summary === '') {
                Log::warning('ai_assist.opportunity_summary_invalid_output');

                return null;
            }

            return ['summary' => $summary];
        } catch (Throwable $e) {
            Log::error('ai_assist.opportunity_summary_exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/AiAssistServiceTest.php`
Expected: PASS (existing tests + 4 new).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AiAssistService.php tests/Unit/AiAssistServiceTest.php
git commit -m "feat(ai): add opportunity summary method to AiAssistService"
```

---

### Task 2: Anonymized context builder on `CrmPageController`

**Files:**
- Modify: `app/Http/Controllers/Web/CrmPageController.php` (add one public method, placed near `suggestLeadConversion()` around line 161)
- Test: `tests/Unit/OpportunitySummaryContextTest.php` (new file)

**Interfaces:**
- Consumes: `App\Models\Opportunity`, `App\Models\Lead`, `App\Models\OpportunityAppointment`, `App\Models\Quote` (all existing).
- Produces: `public function buildOpportunitySummaryContext(Opportunity $opportunity): array` — the ONLY assembler of data sent to the AI. Task 3's endpoint calls it; the whitelist unit test locks its shape.

- [ ] **Step 1: Write the failing whitelist test**

Create `tests/Unit/OpportunitySummaryContextTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Web\CrmPageController;
use App\Models\Account;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityAppointment;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * The control test for goal #8 anonymization: the context sent to the AI is a
 * strict whitelist. Any new field must be added here deliberately — nothing
 * leaks by default.
 */
class OpportunitySummaryContextTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_context_contains_exactly_the_whitelisted_keys(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Anh Minh - 0901234567',
        ]);

        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Biệt thự anh Minh Quận 2',
            'service_category' => 'interior',
            'service_scope_summary' => 'Nội thất biệt thự 3 tầng',
            'pipeline_stage' => Opportunity::STAGE_PROPOSAL_SENT,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        Lead::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contact_hint' => 'Anh Minh - 0901234567',
            'project_description' => 'Biệt thự 3 tầng cần thiết kế nội thất',
            'source' => 'zalo',
            'status' => Lead::STATUS_CONVERTED,
            'captured_by' => (string) $user->id,
            'converted_opportunity_id' => (string) $opportunity->id,
        ]);

        OpportunityAppointment::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
            'type' => OpportunityAppointment::TYPE_CONSULTATION,
            'scheduled_at' => '2026-07-15 09:00:00',
            'location' => '12 Trần Não, Quận 2',
            'status' => OpportunityAppointment::STATUS_COMPLETED,
            'outcome_notes' => 'Khách muốn phong cách tối giản',
            'created_by' => (string) $user->id,
        ]);

        Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
            'quote_number' => Quote::nextNumber((string) $tenant->id),
            'revision_no' => Quote::nextRevision((string) $opportunity->id),
            'status' => Quote::STATUS_DRAFT,
            'total' => 250000000,
            'created_by' => (string) $user->id,
        ]);

        $context = (new CrmPageController())->buildOpportunitySummaryContext($opportunity->fresh());

        // Top level: exactly these keys.
        $this->assertSame(
            ['opportunity', 'lead_origin', 'appointments', 'quotes'],
            array_keys($context)
        );

        // Opportunity block: exact whitelist — opportunity_name MUST be absent.
        $this->assertSame(
            [
                'service_category', 'service_scope_summary', 'pipeline_stage',
                'forecast_category', 'estimated_fee', 'estimated_project_value',
                'probability', 'expected_close_date', 'priority', 'lost_reason', 'created_at',
            ],
            array_keys($context['opportunity'])
        );

        // Lead origin: exact whitelist.
        $this->assertSame(['project_description', 'created_at'], array_keys($context['lead_origin']));

        // Each appointment: exact whitelist — location MUST be absent.
        $this->assertCount(1, $context['appointments']);
        $this->assertSame(
            ['type', 'scheduled_at', 'status', 'outcome_notes'],
            array_keys($context['appointments'][0])
        );

        // Each quote: exact whitelist.
        $this->assertCount(1, $context['quotes']);
        $this->assertSame(
            ['quote_number', 'revision_no', 'status', 'total', 'sent_at', 'decided_at', 'valid_until'],
            array_keys($context['quotes'][0])
        );

        // Belt-and-braces: no identity strings anywhere in the payload.
        $json = (string) json_encode($context, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Biệt thự anh Minh Quận 2', $json);
        $this->assertStringNotContainsString('0901234567', $json);
        $this->assertStringNotContainsString('12 Trần Não', $json);
    }

    public function test_lead_origin_is_null_without_converted_lead(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Test Account',
        ]);

        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Direct Opp',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        $context = (new CrmPageController())->buildOpportunitySummaryContext($opportunity);

        $this->assertNull($context['lead_origin']);
        $this->assertSame([], $context['appointments']);
        $this->assertSame([], $context['quotes']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/OpportunitySummaryContextTest.php`
Expected: FAIL — `Call to undefined method ...::buildOpportunitySummaryContext()`.

- [ ] **Step 3: Implement the builder**

In `app/Http/Controllers/Web/CrmPageController.php`, add after `suggestLeadConversion()`:

```php
    /**
     * Build the anonymized context sent to the AI for an opportunity summary.
     * STRICT WHITELIST (spec: 2026-07-19-goal8-opportunity-ai-summary-design.md, "Data policy"):
     * never include opportunity_name, appointment location, or any account identity field.
     * tests/Unit/OpportunitySummaryContextTest.php asserts the exact key set.
     *
     * @return array{opportunity: array<string, mixed>, lead_origin: array<string, mixed>|null, appointments: list<array<string, mixed>>, quotes: list<array<string, mixed>>}
     */
    public function buildOpportunitySummaryContext(\App\Models\Opportunity $opportunity): array
    {
        $tenantId = (string) $opportunity->tenant_id;

        $lead = \App\Models\Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('converted_opportunity_id', (string) $opportunity->id)
            ->first();

        $appointments = \App\Models\OpportunityAppointment::query()
            ->where('tenant_id', $tenantId)
            ->where('opportunity_id', (string) $opportunity->id)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (\App\Models\OpportunityAppointment $a) => [
                'type' => $a->type,
                'scheduled_at' => $a->scheduled_at?->toDateTimeString(),
                'status' => $a->status,
                'outcome_notes' => $a->outcome_notes,
            ])
            ->values()
            ->all();

        $quotes = \App\Models\Quote::query()
            ->where('tenant_id', $tenantId)
            ->where('opportunity_id', (string) $opportunity->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (\App\Models\Quote $q) => [
                'quote_number' => $q->quote_number,
                'revision_no' => $q->revision_no,
                'status' => $q->status,
                'total' => $q->total,
                'sent_at' => $q->sent_at?->toDateTimeString(),
                'decided_at' => $q->decided_at?->toDateTimeString(),
                'valid_until' => $q->valid_until?->toDateString(),
            ])
            ->values()
            ->all();

        return [
            'opportunity' => [
                'service_category' => $opportunity->service_category,
                'service_scope_summary' => $opportunity->service_scope_summary,
                'pipeline_stage' => $opportunity->pipeline_stage,
                'forecast_category' => $opportunity->forecast_category,
                'estimated_fee' => $opportunity->estimated_fee,
                'estimated_project_value' => $opportunity->estimated_project_value,
                'probability' => $opportunity->probability,
                'expected_close_date' => $opportunity->expected_close_date,
                'priority' => $opportunity->priority,
                'lost_reason' => $opportunity->lost_reason,
                'created_at' => $opportunity->created_at?->toDateTimeString(),
            ],
            'lead_origin' => $lead === null ? null : [
                'project_description' => $lead->project_description,
                'created_at' => $lead->created_at?->toDateTimeString(),
            ],
            'appointments' => $appointments,
            'quotes' => $quotes,
        ];
    }
```

Note: if PHPStan later complains about property access on these models, that is the pre-existing no-Larastan situation — add surgical baseline entries only for the new lines, matching existing entries for this file; never regenerate the whole baseline.

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/OpportunitySummaryContextTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Web/CrmPageController.php tests/Unit/OpportunitySummaryContextTest.php
git commit -m "feat(crm): anonymized whitelist context builder for opportunity AI summary"
```

---

### Task 3: Endpoint + route + feature tests

**Files:**
- Modify: `app/Http/Controllers/Web/CrmPageController.php` (add endpoint method after `buildOpportunitySummaryContext()`)
- Modify: `routes/web.php` (one route, next to the existing line ~1031 `crm.leads.suggest-conversion` route)
- Test: `tests/Feature/Zena/AiOpportunitySummaryTest.php` (new file)

**Interfaces:**
- Consumes: `AiAssistService::summarizeOpportunity(array): ?array` (Task 1), `buildOpportunitySummaryContext()` (Task 2).
- Produces: route `operator.crm.opportunities.ai-summary` (POST `/crm/opportunities/{id}/ai-summary`). Task 4's JS calls `/operator/crm/opportunities/{id}/ai-summary`.

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/Zena/AiOpportunitySummaryTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class AiOpportunitySummaryTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private Tenant $tenant;
    private User $user;
    private Opportunity $opportunity;

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
            ['crm.view', 'ai.suggest']
        );

        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Test Account',
        ]);

        $this->opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Test Opp',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        // Establish a real session so CSRF token resolution works for web POSTs
        // (TestCase deliberately refuses to fabricate sessions — 2026-07-15 regression note).
        $this->get('/login');
    }

    public function test_returns_summary_for_authorized_user(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'summarize_opportunity',
                    'input' => ['summary' => '- Cơ hội mới, chưa có báo giá'],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(
            route('operator.crm.opportunities.ai-summary', $this->opportunity->id)
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.summary', '- Cơ hội mới, chưa có báo giá');
        $this->assertNotEmpty($response->json('data.generated_at'));
    }

    public function test_returns_503_when_ai_disabled(): void
    {
        config(['ai.anthropic_api_key' => '']);

        $response = $this->actingAs($this->user)->postJson(
            route('operator.crm.opportunities.ai-summary', $this->opportunity->id)
        );

        $response->assertStatus(503);
        $response->assertJsonPath('success', false);
    }

    public function test_requires_ai_suggest_permission(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['member'], ['crm.view']);

        $this->actingAs($viewer)->postJson(
            route('operator.crm.opportunities.ai-summary', $this->opportunity->id)
        )->assertStatus(403);
    }

    public function test_requires_crm_view_permission(): void
    {
        $noCrm = $this->createTenantUser($this->tenant, [], ['member'], ['ai.suggest']);

        $this->actingAs($noCrm)->postJson(
            route('operator.crm.opportunities.ai-summary', $this->opportunity->id)
        )->assertStatus(403);
    }

    public function test_returns_404_for_other_tenants_opportunity(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['admin'], ['crm.view', 'ai.suggest']);

        $this->actingAs($otherUser)->postJson(
            route('operator.crm.opportunities.ai-summary', $this->opportunity->id)
        )->assertStatus(404);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Zena/AiOpportunitySummaryTest.php`
Expected: FAIL — route `operator.crm.opportunities.ai-summary` not defined.

- [ ] **Step 3: Add the route**

In `routes/web.php`, immediately after the existing line (~1031):

```php
    Route::post('/crm/leads/{id}/suggest-conversion', [App\Http\Controllers\Web\CrmPageController::class, 'suggestLeadConversion'])->middleware(['rbac:crm.manage', 'rbac:ai.suggest', 'throttle:ai-suggest'])->name('crm.leads.suggest-conversion');
```

add:

```php
    Route::post('/crm/opportunities/{id}/ai-summary', [App\Http\Controllers\Web\CrmPageController::class, 'summarizeOpportunity'])->middleware(['rbac:crm.view', 'rbac:ai.suggest', 'throttle:ai-suggest'])->name('crm.opportunities.ai-summary');
```

- [ ] **Step 4: Add the endpoint method**

In `app/Http/Controllers/Web/CrmPageController.php`, add directly after `buildOpportunitySummaryContext()`:

```php
    public function summarizeOpportunity(Request $request, string $id, AiAssistService $aiAssistService): JsonResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $opportunity = \App\Models\Opportunity::query()->forTenant($tenantId)->whereKey($id)->first();

        if (!$opportunity instanceof \App\Models\Opportunity) {
            return response()->json(['success' => false, 'message' => 'Opportunity not found'], 404);
        }

        $summary = $aiAssistService->summarizeOpportunity(
            $this->buildOpportunitySummaryContext($opportunity)
        );

        if ($summary === null) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo tóm tắt lúc này.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary['summary'],
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
```

(`Request`, `JsonResponse`, and `AiAssistService` are already imported at the top of this file for `suggestLeadConversion` — do not add duplicate imports.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Zena/AiOpportunitySummaryTest.php`
Expected: PASS (5 tests).

- [ ] **Step 6: Regression check on the AI rate-limit and lead-suggestion suites**

Run: `./vendor/bin/phpunit tests/Feature/Zena/AiLeadSuggestionTest.php tests/Feature/Zena/AiSuggestRateLimitTest.php tests/Unit/AiAssistServiceTest.php`
Expected: PASS — nothing existing changed behavior.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Web/CrmPageController.php routes/web.php tests/Feature/Zena/AiOpportunitySummaryTest.php
git commit -m "feat(crm): AI opportunity summary endpoint"
```

---

### Task 4: UI — Blade card + JS

**Files:**
- Modify: `resources/views/crm/opportunity-show.blade.php` (add one card after the "Báo giá (native)" card, which ends around line 348)
- Create: `resources/js/ai-opportunity-summary.js`
- Modify: `vite.config.js` (add the new JS file to the `input` array, line ~7)
- Modify: `resources/views/layouts/operator.blade.php` (add the new JS file to the `@vite([...])` array, line ~10)

**Interfaces:**
- Consumes: route `operator.crm.opportunities.ai-summary` (Task 3).
- Produces: nothing consumed later — final task.

This task is UI-only glue over an already-tested endpoint; no new backend test (same rationale as this codebase's other AI-button views — `leads.blade.php` has no dedicated JS test either). Manual verification steps are listed instead.

- [ ] **Step 1: Add the Blade card**

In `resources/views/crm/opportunity-show.blade.php`, after the closing `</x-ui.card>` of the "Báo giá (native)" card (~line 348), add:

```blade
    @if (auth()->user()?->hasPermission('ai.suggest'))
        <x-ui.card title="Tóm tắt AI">
            <div data-ai-summary data-opportunity-id="{{ $opportunity->id }}">
                <p class="text-sm text-slate-500">AI tóm tắt cơ hội này từ lead gốc, lịch hẹn và báo giá — dùng để chuẩn bị trước cuộc gặp khách.</p>
                <button type="button" class="operator-button operator-button-secondary mt-2" data-ai-summary-trigger>Tạo tóm tắt</button>
                <span class="text-xs text-slate-500" data-ai-summary-status></span>
                <div class="mt-3 hidden whitespace-pre-line text-sm text-slate-700" data-ai-summary-result></div>
                <p class="mt-2 hidden text-xs text-slate-400" data-ai-summary-caption></p>
            </div>
        </x-ui.card>
    @endif
```

- [ ] **Step 2: Create the JS file**

Create `resources/js/ai-opportunity-summary.js`:

```javascript
/**
 * Nút "Tạo tóm tắt" trên trang cơ hội: gọi endpoint AI summary và hiển thị
 * kết quả tại chỗ (không lưu). Copy pattern từ ai-lead-suggest.js.
 */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function attach(container) {
        if (container.dataset.aiSummaryBound) return;
        container.dataset.aiSummaryBound = '1';

        var trigger = container.querySelector('[data-ai-summary-trigger]');
        var statusEl = container.querySelector('[data-ai-summary-status]');
        var resultEl = container.querySelector('[data-ai-summary-result]');
        var captionEl = container.querySelector('[data-ai-summary-caption]');
        var opportunityId = container.dataset.opportunityId;

        if (!trigger || !opportunityId) return;

        trigger.addEventListener('click', function () {
            trigger.disabled = true;
            if (statusEl) statusEl.textContent = 'Đang tạo tóm tắt...';

            fetch('/operator/crm/opportunities/' + encodeURIComponent(opportunityId) + '/ai-summary', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.body.success) {
                        if (statusEl) statusEl.textContent = 'AI chưa bật hoặc đang lỗi — thử lại sau.';
                        return;
                    }

                    if (statusEl) statusEl.textContent = '';
                    if (resultEl) {
                        resultEl.textContent = result.body.data.summary;
                        resultEl.classList.remove('hidden');
                    }
                    if (captionEl) {
                        var at = new Date(result.body.data.generated_at);
                        captionEl.textContent = 'Tạo lúc ' + at.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
                            + ' — AI chỉ tóm tắt từ dữ liệu CRM, hãy kiểm chứng trước khi trao đổi với khách.';
                        captionEl.classList.remove('hidden');
                    }
                    trigger.textContent = 'Tạo lại';
                })
                .catch(function () {
                    if (statusEl) statusEl.textContent = 'AI chưa bật hoặc đang lỗi — thử lại sau.';
                })
                .finally(function () {
                    trigger.disabled = false;
                });
        });
    }

    function init() {
        document.querySelectorAll('[data-ai-summary]').forEach(attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

- [ ] **Step 3: Register the JS file**

In `vite.config.js` line ~7, add `'resources/js/ai-opportunity-summary.js'` to the existing `input` array (after `'resources/js/ai-design-item-suggest.js'`).

In `resources/views/layouts/operator.blade.php` line ~10, add `'resources/js/ai-opportunity-summary.js'` to the existing `@vite([...])` array (after `'resources/js/ai-design-item-suggest.js'`).

- [ ] **Step 4: Verify compile + full test sweep**

Run: `php artisan view:cache` — expected: "Blade templates cached successfully", no errors.
Run: `./vendor/bin/phpunit tests/Unit/AiAssistServiceTest.php tests/Unit/OpportunitySummaryContextTest.php tests/Feature/Zena/AiOpportunitySummaryTest.php` — expected: all PASS.

Manual verification (if a browser + dev server is available; otherwise state explicitly that this was not executed):
1. Open an opportunity with at least one appointment and one quote; as a user with `ai.suggest`, the "Tóm tắt AI" card is visible.
2. Click "Tạo tóm tắt" → loading → summary bullets appear with the timestamp caption; button becomes "Tạo lại".
3. As a user without `ai.suggest`, the card does not render at all.

- [ ] **Step 5: Commit**

```bash
git add resources/views/crm/opportunity-show.blade.php resources/js/ai-opportunity-summary.js vite.config.js resources/views/layouts/operator.blade.php
git commit -m "feat(crm): AI summary card on opportunity page"
```
